<?php

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can each list alerts for their organization', function (?string $factoryState) {
    $organization = Organization::factory()->create();
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();
    alertFor($organization);
    alertFor($organization);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson('/api/v1/alerts')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'prediction_id', 'severity', 'status', 'created_at']], 'pagination']);
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

test('alerts can be filtered by status and severity', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $open = alertFor($organization, ['status' => AlertStatus::Open, 'severity' => Severity::High]);
    alertFor($organization, ['status' => AlertStatus::Resolved, 'severity' => Severity::Low]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/alerts?status=OPEN&severity=HIGH')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);
});

// === DATA ISOLATION ===

test('an organization never sees another organizations alerts in the list', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    alertFor($organizationB);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/alerts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot list alerts', function () {
    $this->getJson('/api/v1/alerts')->assertUnauthorized();
});

test('an agent (API key) cannot list alerts', function () {
    $rawKey = 'sk_live_alert_list_test';
    createAgentWithKey($rawKey);

    $this->withHeader('X-Api-Key', $rawKey)
        ->getJson('/api/v1/alerts')
        ->assertUnauthorized();
});

// === BUSINESS RULE ===

test('no POST or DELETE route exists for alerts', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->postJson('/api/v1/alerts', [])->assertStatus(405);
    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))->deleteJson('/api/v1/alerts/0198a1b2-0000-7000-8000-000000000000')->assertStatus(405);
});

test('no reopen route exists for alerts', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $alert = alertFor($organization);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson("/api/v1/alerts/{$alert->id}/reopen")
        ->assertStatus(404);
});
