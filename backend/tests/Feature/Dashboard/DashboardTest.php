<?php

use App\Modules\Agent\Application\Contracts\AgentSummaryContract;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Application\Contracts\AlertSummaryContract;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Application\Contracts\PredictionStatsContract;
use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Application\Contracts\ObservationSummaryContract;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

use function Pest\Laravel\mock;

/**
 * Binds all four Dashboard-consumed contracts to Mockery mocks returning
 * fixed, known values — used by the field-provenance tests to prove each
 * response field traces to exactly one contract, independent of real
 * database state.
 *
 * @param  array<string, mixed>  $overrides
 */
function mockDashboardContracts(array $overrides = []): void
{
    $values = [
        'agentTotal' => 0,
        'agentActive' => 0,
        'agentList' => [],
        'obsCount' => 0,
        'obsList' => [],
        'riskSummary' => ['SAFE' => 0, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0],
        'alertCounts' => ['OPEN' => 0, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 0],
        'alertList' => [],
        ...$overrides,
    ];

    mock(AgentSummaryContract::class, function ($mock) use ($values) {
        $mock->shouldReceive('countTotalForOrganization')->andReturn($values['agentTotal']);
        $mock->shouldReceive('countActiveForOrganization')->andReturn($values['agentActive']);
        $mock->shouldReceive('listRecentlyActiveForOrganization')->andReturn($values['agentList']);
    });

    mock(ObservationSummaryContract::class, function ($mock) use ($values) {
        $mock->shouldReceive('countForOrganizationSince')->andReturn($values['obsCount']);
        $mock->shouldReceive('listRecentForOrganization')->andReturn($values['obsList']);
    });

    mock(PredictionStatsContract::class, function ($mock) use ($values) {
        $mock->shouldReceive('verdictDistributionForOrganization')->andReturn($values['riskSummary']);
    });

    mock(AlertSummaryContract::class, function ($mock) use ($values) {
        $mock->shouldReceive('countByStatusForOrganization')->andReturn($values['alertCounts']);
        $mock->shouldReceive('listRecentForOrganization')->andReturn($values['alertList']);
    });
}

// === HAPPY PATH ===

test('GET /dashboard returns all five sections, correctly populated, from real data', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    Agent::factory()->for($organization)->create(['status' => 'ACTIVE', 'last_seen_at' => now()->subMinutes(5)]);
    Agent::factory()->for($organization)->create(['status' => 'ARCHIVED']);

    $observation = Observation::factory()->for($organization)->create(['received_at' => now()->subDays(2)]);
    Prediction::factory()->for($observation)->create(['verdict' => Verdict::Malicious, 'risk_score' => 90]);

    $safeObservation = Observation::factory()->for($organization)->create(['received_at' => now()->subDays(3)]);
    Prediction::factory()->for($safeObservation)->create(['verdict' => Verdict::Safe, 'risk_score' => 5]);

    $alertPrediction = Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Suspicious]);
    Alert::factory()->for($alertPrediction)->create(['status' => AlertStatus::Open]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'organization_stats' => ['total_agents', 'active_agents', 'total_observations_last_30_days', 'open_alerts'],
                'active_agents',
                'recent_observations',
                'recent_alerts',
                'risk_summary' => ['SAFE', 'SUSPICIOUS', 'MALICIOUS'],
            ],
        ]);

    $response->assertJsonPath('data.organization_stats.total_agents', 2)
        ->assertJsonPath('data.organization_stats.active_agents', 1)
        ->assertJsonPath('data.organization_stats.total_observations_last_30_days', 3)
        ->assertJsonPath('data.organization_stats.open_alerts', 1)
        ->assertJsonPath('data.risk_summary.SAFE', 1)
        ->assertJsonPath('data.risk_summary.SUSPICIOUS', 1)
        ->assertJsonPath('data.risk_summary.MALICIOUS', 1)
        ->assertJsonCount(1, 'data.active_agents')
        ->assertJsonCount(3, 'data.recent_observations')
        ->assertJsonCount(1, 'data.recent_alerts');
});

// === EDGE CASE ===

test('a brand-new, empty organization returns a well-formed response with zeros and empty arrays', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.organization_stats.total_agents', 0)
        ->assertJsonPath('data.organization_stats.active_agents', 0)
        ->assertJsonPath('data.organization_stats.total_observations_last_30_days', 0)
        ->assertJsonPath('data.organization_stats.open_alerts', 0)
        ->assertJsonPath('data.active_agents', [])
        ->assertJsonPath('data.recent_observations', [])
        ->assertJsonPath('data.recent_alerts', [])
        ->assertJsonPath('data.risk_summary', ['SAFE' => 0, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0]);
});

test('an organization with fewer than 5 recent items returns exactly that many, not padded', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Observation::factory(2)->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(2, 'data.recent_observations');
});

test('an organization with more than 5 recent observations still returns only 5', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Observation::factory(8)->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonCount(5, 'data.recent_observations');
});

test('risk_summary includes a zero count for a verdict that has never occurred for this organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->create();
    Prediction::factory()->for($observation)->create(['verdict' => Verdict::Safe]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.risk_summary.SAFE', 1)
        ->assertJsonPath('data.risk_summary.SUSPICIOUS', 0)
        ->assertJsonPath('data.risk_summary.MALICIOUS', 0);
});

// === BUSINESS RULE (field provenance) ===

test('changing only AgentSummaryContract changes only agent-derived fields', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mockDashboardContracts(['agentTotal' => 3, 'agentActive' => 2]);
    $baseline = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    mockDashboardContracts(['agentTotal' => 99, 'agentActive' => 88]);
    $varied = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    expect($varied['organization_stats']['total_agents'])->toBe(99)
        ->and($varied['organization_stats']['active_agents'])->toBe(88)
        ->and($varied['organization_stats']['total_observations_last_30_days'])->toBe($baseline['organization_stats']['total_observations_last_30_days'])
        ->and($varied['organization_stats']['open_alerts'])->toBe($baseline['organization_stats']['open_alerts'])
        ->and($varied['recent_observations'])->toBe($baseline['recent_observations'])
        ->and($varied['recent_alerts'])->toBe($baseline['recent_alerts'])
        ->and($varied['risk_summary'])->toBe($baseline['risk_summary']);
});

test('changing only ObservationSummaryContract changes only the observation-derived field', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mockDashboardContracts(['obsCount' => 4]);
    $baseline = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    mockDashboardContracts(['obsCount' => 777]);
    $varied = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    expect($varied['organization_stats']['total_observations_last_30_days'])->toBe(777)
        ->and($varied['organization_stats']['total_agents'])->toBe($baseline['organization_stats']['total_agents'])
        ->and($varied['organization_stats']['active_agents'])->toBe($baseline['organization_stats']['active_agents'])
        ->and($varied['organization_stats']['open_alerts'])->toBe($baseline['organization_stats']['open_alerts'])
        ->and($varied['active_agents'])->toBe($baseline['active_agents'])
        ->and($varied['recent_alerts'])->toBe($baseline['recent_alerts'])
        ->and($varied['risk_summary'])->toBe($baseline['risk_summary']);
});

test('changing only PredictionStatsContract changes only risk_summary', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mockDashboardContracts(['riskSummary' => ['SAFE' => 1, 'SUSPICIOUS' => 2, 'MALICIOUS' => 3]]);
    $baseline = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    mockDashboardContracts(['riskSummary' => ['SAFE' => 40, 'SUSPICIOUS' => 50, 'MALICIOUS' => 60]]);
    $varied = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    expect($varied['risk_summary'])->toBe(['SAFE' => 40, 'SUSPICIOUS' => 50, 'MALICIOUS' => 60])
        ->and($varied['organization_stats'])->toBe($baseline['organization_stats'])
        ->and($varied['active_agents'])->toBe($baseline['active_agents'])
        ->and($varied['recent_observations'])->toBe($baseline['recent_observations'])
        ->and($varied['recent_alerts'])->toBe($baseline['recent_alerts']);
});

test('changing only AlertSummaryContract changes only alert-derived fields', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mockDashboardContracts(['alertCounts' => ['OPEN' => 1, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 0]]);
    $baseline = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    mockDashboardContracts(['alertCounts' => ['OPEN' => 42, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 0]]);
    $varied = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->getJson('/api/v1/dashboard')->json('data');

    expect($varied['organization_stats']['open_alerts'])->toBe(42)
        ->and($varied['organization_stats']['total_agents'])->toBe($baseline['organization_stats']['total_agents'])
        ->and($varied['organization_stats']['total_observations_last_30_days'])->toBe($baseline['organization_stats']['total_observations_last_30_days'])
        ->and($varied['active_agents'])->toBe($baseline['active_agents'])
        ->and($varied['recent_observations'])->toBe($baseline['recent_observations'])
        ->and($varied['risk_summary'])->toBe($baseline['risk_summary']);
});

test('organization_id is passed identically to all four contract calls', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    mock(AgentSummaryContract::class, function ($mock) use ($organization) {
        $mock->shouldReceive('countTotalForOrganization')->once()->with($organization->id)->andReturn(0);
        $mock->shouldReceive('countActiveForOrganization')->once()->with($organization->id)->andReturn(0);
        $mock->shouldReceive('listRecentlyActiveForOrganization')->once()->with($organization->id, 5)->andReturn([]);
    });

    mock(ObservationSummaryContract::class, function ($mock) use ($organization) {
        $mock->shouldReceive('countForOrganizationSince')->once()->withArgs(fn ($orgId) => $orgId === $organization->id)->andReturn(0);
        $mock->shouldReceive('listRecentForOrganization')->once()->with($organization->id, 5)->andReturn([]);
    });

    mock(PredictionStatsContract::class, function ($mock) use ($organization) {
        $mock->shouldReceive('verdictDistributionForOrganization')->once()->with($organization->id)
            ->andReturn(['SAFE' => 0, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0]);
    });

    mock(AlertSummaryContract::class, function ($mock) use ($organization) {
        $mock->shouldReceive('countByStatusForOrganization')->once()->with($organization->id)
            ->andReturn(['OPEN' => 0, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 0]);
        $mock->shouldReceive('listRecentForOrganization')->once()->with($organization->id, 5)->andReturn([]);
    });

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/dashboard')
        ->assertOk();
});

// === AUTHORIZATION ===

test('owner, admin, and member can each independently view the dashboard', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson('/api/v1/dashboard')
        ->assertOk();
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

test('an unauthenticated request cannot view the dashboard', function () {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
});

test('an agent (API key) cannot view the dashboard', function () {
    $rawKey = 'sk_live_dashboard_test';
    createAgentWithKey($rawKey);

    $this->withHeader('X-Api-Key', $rawKey)
        ->getJson('/api/v1/dashboard')
        ->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization As dashboard never includes organization Bs data', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();

    $agentA = Agent::factory()->for($organizationA)->create(['status' => 'ACTIVE', 'last_seen_at' => now()]);
    Agent::factory(3)->for($organizationB)->create(['status' => 'ACTIVE', 'last_seen_at' => now()]);
    Observation::factory(2)->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.organization_stats.total_agents', 1)
        ->assertJsonPath('data.organization_stats.total_observations_last_30_days', 0)
        ->assertJsonCount(1, 'data.active_agents')
        ->assertJsonPath('data.active_agents.0.id', $agentA->id);
});
