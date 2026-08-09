<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Audit\Infrastructure\Persistence\AuditRepository;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\mock;

/**
 * The single most important test in this Sprint — directly verifies the
 * Golden Rule from 01-overview.md §2 / 03-audit-logging.md §6: a failure
 * while recording an audit event must NEVER cause the triggering action to
 * fail or roll back.
 */
test('a simulated DB error inside the Audit listener does not block or fail Agent creation, which still returns 201', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mock(AuditRepository::class, function ($mock) {
        $mock->shouldReceive('create')->andThrow(new RuntimeException('Simulated DB failure'));
    });

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', ['name' => 'Resilient Agent', 'framework' => 'CrewAI'])
        ->assertCreated();

    expect(Agent::where('organization_id', $organization->id)->where('name', 'Resilient Agent')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'agent.created')->count())->toBe(0);
});

test('a simulated DB error inside the Audit listener is logged, not silently discarded', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mock(AuditRepository::class, function ($mock) {
        $mock->shouldReceive('create')->andThrow(new RuntimeException('Simulated DB failure'));
    });

    Log::spy();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', ['name' => 'Logged Failure Agent', 'framework' => 'CrewAI'])
        ->assertCreated();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message) => $message === 'Failed to record audit log entry.');
});

test('a simulated DB error inside the Audit listener does not block Alert acknowledgement either', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    mock(AuditRepository::class, function ($mock) {
        $mock->shouldReceive('create')->andThrow(new RuntimeException('Simulated DB failure'));
    });

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertOk()
        ->assertJsonPath('data.status', 'ACKNOWLEDGED');

    expect($alert->fresh()->status)->toBe(AlertStatus::Acknowledged);
});
