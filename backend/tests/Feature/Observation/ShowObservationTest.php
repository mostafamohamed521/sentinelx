<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can all view a single observation, with prediction always null', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    $observation = Observation::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson("/api/v1/observations/{$observation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $observation->id)
        ->assertJsonPath('data.prediction', null)
        ->assertJsonPath('data.raw_ases_json', $observation->raw_ases_json);
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

// === DATA ISOLATION ===

test('an organization cannot view another organizations observation', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $observationB = Observation::factory()->for($organizationB)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/observations/{$observationB->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('a non-existent observation id returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/observations/0198a1b2-0000-7000-8000-000000000000')
        ->assertNotFound();
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot view an observation', function () {
    $observation = Observation::factory()->create();

    $this->getJson("/api/v1/observations/{$observation->id}")->assertUnauthorized();
});
