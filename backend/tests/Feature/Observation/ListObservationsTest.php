<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can all list observations for their organization', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    Observation::factory(3)->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson('/api/v1/observations')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('pagination.total_items', 3);
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

test('the list response excludes raw_ases_json', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Observation::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/observations')
        ->assertOk()
        ->assertJsonMissingPath('data.0.raw_ases_json');
});

// === DATA ISOLATION ===

test('an organizations observation list never includes another organizations observations', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    Observation::factory(2)->for($organizationA)->create();
    Observation::factory(5)->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/observations')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// === AUTHORIZATION ===

test('a valid api key with no jwt cannot list observations', function () {
    createAgentWithKey('a-valid-raw-secret');

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->getJson('/api/v1/observations')
        ->assertUnauthorized();
});

test('an unauthenticated request cannot list observations', function () {
    $this->getJson('/api/v1/observations')->assertUnauthorized();
});
