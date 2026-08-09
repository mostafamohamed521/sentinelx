<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === PERF-003: every collection endpoint clamps per_page to the same maximum ===

test('every collection endpoint clamps an excessive per_page down to the documented maximum of 100', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    $auth = ['Authorization' => 'Bearer '.tokenFor($owner)];

    $cases = [
        'Agents' => fn () => $this->withHeaders($auth)->getJson('/api/v1/agents?per_page=5000'),
        'Alerts' => fn () => $this->withHeaders($auth)->getJson('/api/v1/alerts?per_page=5000'),
        'AuditLogs' => fn () => $this->withHeaders($auth)->getJson('/api/v1/audit-logs?per_page=5000'),
        'SecurityLogs' => fn () => $this->withHeaders($auth)->getJson('/api/v1/security-logs?per_page=5000'),
        'AgentObservations' => fn () => $this->withHeaders($auth)->getJson("/api/v1/agents/{$agent->id}/observations?per_page=5000"),
        'Observations' => fn () => $this->withHeaders($auth)->getJson('/api/v1/observations?per_page=5000'),
    ];

    foreach ($cases as $name => $makeRequest) {
        $response = $makeRequest();

        $response->assertOk();
        expect($response->json('pagination.per_page'))->toBe(100, "{$name}'s pagination.per_page");
    }
});

test('per_page below the maximum is honored as requested, unchanged', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/agents?per_page=5');

    $response->assertOk()->assertJsonPath('pagination.per_page', 5);
});
