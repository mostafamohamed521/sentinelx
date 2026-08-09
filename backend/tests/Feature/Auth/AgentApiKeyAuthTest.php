<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// createAgentWithKey() is a shared helper — see tests/Pest.php.

// === HAPPY PATH ===

test('a valid, active API key authenticates the agent', function () {
    $agent = createAgentWithKey('a-valid-raw-secret');

    $response = $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->getJson('/api/agent/me');

    $response->assertOk()->assertJsonPath('data.id', $agent->id);
});

test('successful authentication touches last_used_at and last_seen_at', function () {
    $agent = createAgentWithKey('a-valid-raw-secret', ['last_seen_at' => null]);

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')->getJson('/api/agent/me')->assertOk();

    expect($agent->fresh()->last_seen_at)->not->toBeNull()
        ->and(ApiKey::where('agent_id', $agent->id)->first()->last_used_at)->not->toBeNull();
});

// === FAILURE CASES — all must return the exact same generic 401 shape ===

test('a request with no API key is rejected', function () {
    $this->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('an unknown API key is rejected', function () {
    $this->withHeader('X-API-Key', 'not-a-real-key')
        ->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('a revoked API key is rejected', function () {
    createAgentWithKey('a-revoked-secret', keyState: ['status' => ApiKeyStatus::Revoked]);

    $this->withHeader('X-API-Key', 'a-revoked-secret')
        ->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('an expired API key is rejected', function () {
    createAgentWithKey('an-expired-secret', keyState: ['expires_at' => now()->subDay()]);

    $this->withHeader('X-API-Key', 'an-expired-secret')
        ->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('a valid key belonging to an archived agent is rejected', function () {
    createAgentWithKey('a-key-for-archived-agent', ['status' => AgentStatus::Archived]);

    $this->withHeader('X-API-Key', 'a-key-for-archived-agent')
        ->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('a valid key belonging to an Agent in a suspended Organization is rejected (STATE-002)', function () {
    $organization = Organization::factory()->suspended()->create();
    createAgentWithKey('a-key-for-suspended-org', ['organization_id' => $organization->id]);

    $this->withHeader('X-API-Key', 'a-key-for-suspended-org')
        ->getJson('/api/agent/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('an active key for a different agent does not authenticate as another agent', function () {
    createAgentWithKey('agent-one-secret');
    $agentTwo = createAgentWithKey('agent-two-secret');

    $response = $this->withHeader('X-API-Key', 'agent-two-secret')->getJson('/api/agent/me');

    $response->assertOk()->assertJsonPath('data.id', $agentTwo->id);
});
