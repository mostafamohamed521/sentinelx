<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('a human can list observations for a specific agent', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    $otherAgent = Agent::factory()->for($organization)->create();
    Observation::factory(2)->for($organization)->for($agent)->create();
    Observation::factory(4)->for($organization)->for($otherAgent)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/agents/{$agent->id}/observations")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// === DATA ISOLATION ===

test('requesting observations for another organizations agent returns 404', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $agentB = Agent::factory()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/agents/{$agentB->id}/observations")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('a non-existent agent id returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/agents/0198a1b2-0000-7000-8000-000000000000/observations')
        ->assertNotFound();
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot list an agents observations', function () {
    $agent = Agent::factory()->create();

    $this->getJson("/api/v1/agents/{$agent->id}/observations")->assertUnauthorized();
});
