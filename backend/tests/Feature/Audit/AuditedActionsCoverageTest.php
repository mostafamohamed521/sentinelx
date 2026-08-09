<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * One entry per audited action from 03-audit-logging.md §3-4. Each closure
 * performs the real HTTP flow that should produce exactly one audit_logs
 * row with that action string — a single, table-driven test rather than
 * ~14 near-identical copy-pasted test methods.
 */
test('every audited action produces exactly one correctly-shaped audit_logs row', function (Closure $scenario) {
    $action = $scenario();

    $matching = AuditLog::where('action', $action)->get();

    expect($matching)->toHaveCount(1);

    $entry = $matching->first();

    expect($entry->organization_id)->not->toBeNull()
        ->and($entry->actor_type)->not->toBeNull()
        ->and($entry->resource_type)->not->toBeNull()
        ->and($entry->created_at)->not->toBeNull();
})->with([
    'organization.updated' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->patchJson('/api/v1/organization', ['name' => 'Renamed Org'])
            ->assertOk();

        return 'organization.updated';
    },

    'user.registered' => function () {
        Notification::fake();

        test()->postJson('/api/v1/auth/register', [
            'organization_name' => 'Coverage Org',
            'full_name' => 'Coverage Owner',
            'email' => 'coverage-owner@acme.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        return 'user.registered';
    },

    'user.logged_in' => function () {
        User::factory()->create([
            'email' => 'login-coverage@acme.example',
            'password_hash' => Hash::make('password123'),
        ]);

        test()->postJson('/api/v1/auth/login', [
            'email' => 'login-coverage@acme.example',
            'password' => 'password123',
        ])->assertOk();

        return 'user.logged_in';
    },

    'user.logged_out' => function () {
        $user = User::factory()->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($user))
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        return 'user.logged_out';
    },

    'user.profile_updated' => function () {
        $user = User::factory()->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($user))
            ->patchJson('/api/v1/me', ['full_name' => 'Coverage Updated'])
            ->assertOk();

        return 'user.profile_updated';
    },

    'user.password_changed' => function () {
        $user = User::factory()->create(['password_hash' => Hash::make('old-password-123')]);

        test()->withHeader('Authorization', 'Bearer '.tokenFor($user))
            ->postJson('/api/v1/me/change-password', [
                'current_password' => 'old-password-123',
                'new_password' => 'new-password-456',
                'new_password_confirmation' => 'new-password-456',
            ])->assertOk();

        return 'user.password_changed';
    },

    'agent.created' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson('/api/v1/agents', ['name' => 'Coverage Agent', 'framework' => 'CrewAI'])
            ->assertCreated();

        return 'agent.created';
    },

    'agent.updated' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $agent = Agent::factory()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->patchJson("/api/v1/agents/{$agent->id}", ['name' => 'Renamed Agent'])
            ->assertOk();

        return 'agent.updated';
    },

    'agent.archived' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $agent = Agent::factory()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->patchJson("/api/v1/agents/{$agent->id}/archive")
            ->assertOk();

        return 'agent.archived';
    },

    'api_key.generated' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $agent = Agent::factory()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
            ->assertCreated();

        return 'api_key.generated';
    },

    'api_key.rotated' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $agent = Agent::factory()->for($organization)->create();

        // First call generates the first key (api_key.generated); the
        // second call is a genuine rotation.
        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
            ->assertCreated();
        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
            ->assertCreated();

        return 'api_key.rotated';
    },

    'api_key.revoked' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $agent = Agent::factory()->for($organization)->create();

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
            ->assertCreated();
        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
            ->assertCreated();

        return 'api_key.revoked';
    },

    'alert.acknowledged' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $alert = alertWithStatusFor($organization, AlertStatus::Open);

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
            ->assertOk();

        return 'alert.acknowledged';
    },

    'alert.resolved' => function () {
        $organization = Organization::factory()->create();
        $owner = User::factory()->owner()->for($organization)->create();
        $alert = alertWithStatusFor($organization, AlertStatus::Open);

        test()->withHeader('Authorization', 'Bearer '.tokenFor($owner))
            ->patchJson("/api/v1/alerts/{$alert->id}/resolve")
            ->assertOk();

        return 'alert.resolved';
    },
]);
