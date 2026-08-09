<?php

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can each acknowledge an OPEN alert', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertOk()
        ->assertJsonPath('data.status', 'ACKNOWLEDGED')
        ->assertJsonPath('data.acknowledged_by', $human->id);

    expect($alert->fresh()->status)->toBe(AlertStatus::Acknowledged);
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

// === BUSINESS RULE ===

test('acknowledged_by always matches the authenticated users own id, never client-suppliable', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $impostor = User::factory()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge", ['acknowledged_by' => $impostor->id])
        ->assertOk()
        ->assertJsonPath('data.acknowledged_by', $owner->id);

    expect($alert->fresh()->acknowledged_by)->toBe($owner->id)
        ->and($alert->fresh()->acknowledged_by)->not->toBe($impostor->id);
});

// === EDGE CASE ===

test('acknowledging an already-acknowledged alert returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

test('acknowledging an already-resolved alert returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Resolved);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertStatus(409);
});

// === DATA ISOLATION ===

test('an organization cannot acknowledge another organizations alert', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $alertB = alertWithStatusFor($organizationB, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->patchJson("/api/v1/alerts/{$alertB->id}/acknowledge")
        ->assertNotFound();

    expect($alertB->fresh()->status)->toBe(AlertStatus::Open);
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot acknowledge an alert', function () {
    $alert = alertWithStatusFor(Organization::factory()->create(), AlertStatus::Open);

    $this->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")->assertUnauthorized();
});

test('an agent (API key) cannot acknowledge an alert', function () {
    $rawKey = 'sk_live_alert_ack_test';
    $agent = createAgentWithKey($rawKey);
    $alert = alertWithStatusFor($agent->organization, AlertStatus::Open);

    $this->withHeader('X-Api-Key', $rawKey)
        ->patchJson("/api/v1/alerts/{$alert->id}/acknowledge")
        ->assertUnauthorized();
});
