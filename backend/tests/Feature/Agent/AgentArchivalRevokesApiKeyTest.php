<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === BUSINESS RULE (cross-module integration: Agent -> AgentArchived -> API Key submodule) ===

test('archiving an agent revokes its active api key via the AgentArchived event', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    $apiKey = ApiKey::factory()->for($agent)->create(['status' => ApiKeyStatus::Active]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}/archive")
        ->assertOk();

    expect($apiKey->fresh()->status)->toBe(ApiKeyStatus::Revoked);
});

test('a revoked api key can no longer authenticate the agent after archival', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    ApiKey::factory()->for($agent)->create(['status' => ApiKeyStatus::Active, 'key_hash' => hash('sha256', 'archived-agent-secret')]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}/archive")
        ->assertOk();

    $this->withHeader('X-API-Key', 'archived-agent-secret')
        ->getJson('/api/agent/me')
        ->assertUnauthorized();
});
