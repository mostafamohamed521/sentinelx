<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can archive an agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}/archive")
        ->assertOk()
        ->assertJsonPath('data.id', $agent->id)
        ->assertJsonPath('data.status', 'ARCHIVED');

    expect($agent->fresh()->status)->toBe(AgentStatus::Archived);
});

// === EDGE CASES ===

test('archiving an already archived agent returns 409, not a silent success', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create(['status' => AgentStatus::Archived]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/agents/{$agent->id}/archive")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('archiving a non-existent agent returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson('/api/v1/agents/0198a1b2-0000-7000-8000-000000000000/archive')
        ->assertNotFound();
});

// === AUTHORIZATION ===

test('member cannot archive an agent', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->patchJson("/api/v1/agents/{$agent->id}/archive")
        ->assertStatus(403);

    expect($agent->fresh()->status)->toBe(AgentStatus::Active);
});

test('unauthenticated requests to archive an agent are rejected', function () {
    $organization = Organization::factory()->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->patchJson("/api/v1/agents/{$agent->id}/archive")->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization A cannot archive an agent belonging to organization B', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $agentB = Agent::factory()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->patchJson("/api/v1/agents/{$agentB->id}/archive")
        ->assertNotFound();

    expect($agentB->fresh()->status)->toBe(AgentStatus::Active);
});
