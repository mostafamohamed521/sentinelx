<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can rotate an api key for their agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key");

    $response->assertCreated()
        ->assertJsonPath('data.status', 'ACTIVE')
        ->assertJsonStructure(['data' => ['key_prefix', 'raw_key', 'status', 'created_at']]);

    expect(ApiKey::where('agent_id', $agent->id)->where('status', ApiKeyStatus::Active)->count())->toBe(1);
});

test('rotating a second time revokes the first key and issues a new one', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $first = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")->json('data.raw_key');

    $second = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")->json('data.raw_key');

    expect($first)->not->toBe($second)
        ->and(ApiKey::where('agent_id', $agent->id)->count())->toBe(2)
        ->and(ApiKey::where('agent_id', $agent->id)->where('status', ApiKeyStatus::Active)->count())->toBe(1)
        ->and(ApiKey::where('agent_id', $agent->id)->where('status', ApiKeyStatus::Revoked)->count())->toBe(1);
});

test('the raw key returned by rotation authenticates the agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $rawKey = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")->json('data.raw_key');

    $this->withHeader('X-API-Key', $rawKey)
        ->getJson('/api/agent/me')
        ->assertOk()
        ->assertJsonPath('data.id', $agent->id);
});

// === EDGE CASES / BUSINESS RULES ===

test('rotating a key for an archived agent returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create(['status' => AgentStatus::Archived]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('rotating a key for a non-existent agent returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents/0198a1b2-0000-7000-8000-000000000000/rotate-api-key')
        ->assertNotFound();
});

// === AUTHORIZATION ===

test('member cannot rotate an api key', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")
        ->assertStatus(403);
});

test('unauthenticated requests to rotate an api key are rejected', function () {
    $organization = Organization::factory()->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->postJson("/api/v1/agents/{$agent->id}/rotate-api-key")->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization A cannot rotate an api key for an agent belonging to organization B', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $agentB = Agent::factory()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->postJson("/api/v1/agents/{$agentB->id}/rotate-api-key")
        ->assertNotFound();
});
