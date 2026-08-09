<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can view a single agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/agents/{$agent->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $agent->id);
});

test('member can view a single agent', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson("/api/v1/agents/{$agent->id}")
        ->assertOk();
});

// === EDGE CASES ===

test('viewing a non-existent agent returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/agents/0198a1b2-0000-7000-8000-000000000000')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

// === AUTHORIZATION ===

test('unauthenticated requests to view an agent are rejected', function () {
    $organization = Organization::factory()->create();
    $agent = Agent::factory()->for($organization)->create();

    $this->getJson("/api/v1/agents/{$agent->id}")->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization A cannot view an agent belonging to organization B', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $agentB = Agent::factory()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/agents/{$agentB->id}")
        ->assertNotFound();
});
