<?php

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can each resolve an OPEN or ACKNOWLEDGED alert', function (?string $factoryState, AlertStatus $startingStatus) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    $alert = alertWithStatusFor($organization, $startingStatus);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve")
        ->assertOk()
        ->assertJsonPath('data.status', 'RESOLVED')
        ->assertJsonPath('data.resolved_by', $human->id);

    expect($alert->fresh()->status)->toBe(AlertStatus::Resolved);
})->with([
    'owner from OPEN' => ['owner', AlertStatus::Open],
    'admin from OPEN' => ['admin', AlertStatus::Open],
    'member from OPEN' => [null, AlertStatus::Open],
    'owner from ACKNOWLEDGED' => ['owner', AlertStatus::Acknowledged],
]);

test('resolving directly from OPEN (skip-ahead) succeeds without requiring acknowledge first', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve")
        ->assertOk();

    expect($alert->fresh())
        ->status->toBe(AlertStatus::Resolved)
        ->acknowledged_at->toBeNull();
});

// === BUSINESS RULE ===

test('resolved_by always matches the authenticated users own id, never client-suppliable', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $impostor = User::factory()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve", ['resolved_by' => $impostor->id])
        ->assertJsonPath('data.resolved_by', $owner->id);

    expect($alert->fresh()->resolved_by)->toBe($owner->id);
});

// === EDGE CASE ===

test('resolving an already-resolved alert returns 409', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertWithStatusFor($organization, AlertStatus::Resolved);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CONFLICT');
});

// === DATA ISOLATION ===

test('an organization cannot resolve another organizations alert', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $alertB = alertWithStatusFor($organizationB, AlertStatus::Open);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->patchJson("/api/v1/alerts/{$alertB->id}/resolve")
        ->assertNotFound();

    expect($alertB->fresh()->status)->toBe(AlertStatus::Open);
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot resolve an alert', function () {
    $alert = alertWithStatusFor(Organization::factory()->create(), AlertStatus::Open);

    $this->patchJson("/api/v1/alerts/{$alert->id}/resolve")->assertUnauthorized();
});

test('an agent (API key) cannot resolve an alert', function () {
    $rawKey = 'sk_live_alert_resolve_test';
    $agent = createAgentWithKey($rawKey);
    $alert = alertWithStatusFor($agent->organization, AlertStatus::Open);

    $this->withHeader('X-Api-Key', $rawKey)
        ->patchJson("/api/v1/alerts/{$alert->id}/resolve")
        ->assertUnauthorized();
});
