<?php

use App\Modules\Agent\Application\Contracts\AgentSummaryContract;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('countTotalForOrganization counts all agents regardless of status', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create(['status' => 'ACTIVE']);
    Agent::factory()->for($organization)->create(['status' => 'ARCHIVED']);

    expect(app(AgentSummaryContract::class)->countTotalForOrganization($organization->id))->toBe(2);
});

test('countActiveForOrganization counts only ACTIVE agents', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create(['status' => 'ACTIVE']);
    Agent::factory()->for($organization)->create(['status' => 'ARCHIVED']);

    expect(app(AgentSummaryContract::class)->countActiveForOrganization($organization->id))->toBe(1);
});

test('listRecentlyActiveForOrganization orders by last_seen_at descending', function () {
    $organization = Organization::factory()->create();
    $older = Agent::factory()->for($organization)->create(['status' => 'ACTIVE', 'last_seen_at' => now()->subHours(2)]);
    $newer = Agent::factory()->for($organization)->create(['status' => 'ACTIVE', 'last_seen_at' => now()->subMinutes(5)]);

    $result = app(AgentSummaryContract::class)->listRecentlyActiveForOrganization($organization->id, 5);

    expect($result)->toHaveCount(2)
        ->and($result[0]->id)->toBe($newer->id)
        ->and($result[1]->id)->toBe($older->id);
});

// === EDGE CASE ===

test('listRecentlyActiveForOrganization excludes agents that have never sent an observation', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create(['status' => 'ACTIVE', 'last_seen_at' => null]);

    expect(app(AgentSummaryContract::class)->listRecentlyActiveForOrganization($organization->id, 5))->toBe([]);
});

test('listRecentlyActiveForOrganization excludes ARCHIVED agents even if recently seen', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create(['status' => 'ARCHIVED', 'last_seen_at' => now()]);

    expect(app(AgentSummaryContract::class)->listRecentlyActiveForOrganization($organization->id, 5))->toBe([]);
});

test('listRecentlyActiveForOrganization respects the given limit', function () {
    $organization = Organization::factory()->create();
    Agent::factory(3)->for($organization)->create(['status' => 'ACTIVE', 'last_seen_at' => now()]);

    expect(app(AgentSummaryContract::class)->listRecentlyActiveForOrganization($organization->id, 2))->toHaveCount(2);
});

// === DATA ISOLATION ===

test('agent summary counts never include another organizations agents', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    Agent::factory()->for($organizationA)->create(['status' => 'ACTIVE']);
    Agent::factory(4)->for($organizationB)->create(['status' => 'ACTIVE']);

    expect(app(AgentSummaryContract::class)->countTotalForOrganization($organizationA->id))->toBe(1)
        ->and(app(AgentSummaryContract::class)->countActiveForOrganization($organizationA->id))->toBe(1);
});
