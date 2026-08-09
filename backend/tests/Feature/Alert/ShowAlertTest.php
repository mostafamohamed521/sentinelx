<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can each view a single alert with embedded prediction and observation', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    $agent = Agent::factory()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->for($agent)->create();
    $prediction = Prediction::factory()->for($observation)->create();
    $alert = Alert::factory()->for($prediction)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson("/api/v1/alerts/{$alert->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $alert->id)
        ->assertJsonPath('data.severity', $alert->severity->value)
        ->assertJsonPath('data.status', $alert->status->value)
        ->assertJsonPath('data.prediction.id', $prediction->id)
        ->assertJsonPath('data.prediction.verdict', $prediction->verdict->value)
        ->assertJsonPath('data.observation.id', $observation->id)
        ->assertJsonPath('data.observation.agent_id', $agent->id)
        ->assertJsonMissingPath('data.prediction.prediction_json')
        ->assertJsonMissingPath('data.observation.raw_ases_json');
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

// === DATA ISOLATION ===

test('an organization cannot view another organizations alert', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $alertB = alertFor($organizationB);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/alerts/{$alertB->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

test('a non-existent alert id returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/alerts/0198a1b2-0000-7000-8000-000000000000')
        ->assertNotFound();
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot view an alert', function () {
    $alert = alertFor(Organization::factory()->create());

    $this->getJson("/api/v1/alerts/{$alert->id}")->assertUnauthorized();
});
