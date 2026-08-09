<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can update an agent metadata', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create(['name' => 'Support Agent v1']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}", [
            'name' => 'Support Agent v2',
            'description' => 'Now also handles tier-2 escalations',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Support Agent v2')
        ->assertJsonPath('data.description', 'Now also handles tier-2 escalations');
});

// === EDGE CASES ===

test('renaming an agent to a name already used by another agent in the same organization returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Agent::factory()->for($organization)->create(['name' => 'Existing Name']);
    $agent = Agent::factory()->for($organization)->create(['name' => 'Original Name']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}", ['name' => 'Existing Name'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('an empty PATCH body returns 422', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}", [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('updating a non-existent agent returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson('/api/v1/agents/0198a1b2-0000-7000-8000-000000000000', ['name' => 'New Name'])
        ->assertNotFound();
});

// === BUSINESS RULES ===

test('status cannot be set directly via PATCH', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}", ['status' => 'ARCHIVED'])
        ->assertStatus(422);

    expect($agent->fresh()->status)->toBe(AgentStatus::Active);
});

test('organization_id cannot be set via PATCH', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}", ['organization_id' => $otherOrganization->id])
        ->assertStatus(422);
});

// === AUTHORIZATION ===

test('member cannot update an agent', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->patchJson("/api/v1/agents/{$agent->id}", ['name' => 'New Name'])
        ->assertStatus(403);
});

// === DATA ISOLATION ===

test('organization A cannot update an agent belonging to organization B', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $agentB = Agent::factory()->for($organizationB)->create(['name' => 'Original']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->patchJson("/api/v1/agents/{$agentB->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    expect($agentB->fresh()->name)->toBe('Original');
});
