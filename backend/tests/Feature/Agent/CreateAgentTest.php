<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can create an agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', [
            'name' => 'Support Agent',
            'framework' => 'CrewAI',
            'framework_version' => '1.2.0',
            'description' => 'Handles tier-1 customer support tickets',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Support Agent')
        ->assertJsonPath('data.framework', 'CrewAI')
        ->assertJsonPath('data.status', 'ACTIVE')
        ->assertJsonPath('data.last_seen_at', null)
        ->assertJsonMissingPath('data.organization_id');

    expect(Agent::where('organization_id', $organization->id)->where('name', 'Support Agent')->exists())->toBeTrue();
});

// === EDGE CASES ===

test('creating an agent with a duplicate name within the same organization returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Agent::factory()->for($organization)->create(['name' => 'Support Agent']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', ['name' => 'Support Agent', 'framework' => 'CrewAI'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('creating an agent with the same name in a different organization succeeds', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    Agent::factory()->for($organizationA)->create(['name' => 'Support Agent']);
    $ownerB = User::factory()->owner()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerB))
        ->postJson('/api/v1/agents', ['name' => 'Support Agent', 'framework' => 'CrewAI'])
        ->assertCreated();
});

test('creating an agent without a name or framework returns 422', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// === BUSINESS RULES ===

test('organization_id in the request body is rejected, never applied', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', [
            'name' => 'Support Agent',
            'framework' => 'CrewAI',
            'organization_id' => $otherOrganization->id,
        ])
        ->assertStatus(422);

    expect(Agent::where('organization_id', $otherOrganization->id)->exists())->toBeFalse();
});

test('a new agent is always created with status ACTIVE, status cannot be set at creation', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/agents', ['name' => 'Support Agent', 'framework' => 'CrewAI', 'status' => 'ARCHIVED'])
        ->assertStatus(422);
});

// === AUTHORIZATION ===

test('member cannot create an agent', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->postJson('/api/v1/agents', ['name' => 'Support Agent', 'framework' => 'CrewAI'])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('unauthenticated requests to create an agent are rejected', function () {
    $this->postJson('/api/v1/agents', ['name' => 'Support Agent', 'framework' => 'CrewAI'])
        ->assertUnauthorized();
});
