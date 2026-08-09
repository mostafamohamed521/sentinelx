<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can list agents belonging to their organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Agent::factory(3)->for($organization)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/agents');

    $response->assertOk()->assertJsonCount(3, 'data');
    $response->assertJsonStructure(['data', 'pagination' => ['page', 'per_page', 'total_items', 'total_pages']]);
});

test('member can list agents', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    Agent::factory(2)->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson('/api/v1/agents')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// === EDGE CASES ===

test('the status query parameter filters the list', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Agent::factory(2)->for($organization)->create(['status' => AgentStatus::Active]);
    Agent::factory(1)->for($organization)->create(['status' => AgentStatus::Archived]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/agents?status=ARCHIVED')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// === AUTHORIZATION ===

test('unauthenticated requests to list agents are rejected', function () {
    $this->getJson('/api/v1/agents')->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization A never sees organization B agents in the list', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    Agent::factory(2)->for($organizationA)->create();
    Agent::factory(5)->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/agents')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
