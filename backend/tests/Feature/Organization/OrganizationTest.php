<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner, admin, and member can all view their own organization', function (?string $factoryState) {
    $organization = Organization::factory()->create(['name' => 'Acme Security']);
    $humanFactory = User::factory()->for($organization);
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->getJson('/api/v1/organization')
        ->assertOk()
        ->assertJsonPath('data.id', $organization->id)
        ->assertJsonPath('data.name', 'Acme Security')
        ->assertJsonPath('data.slug', $organization->slug)
        ->assertJsonPath('data.status', 'ACTIVE');
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

test('an owner can update the organization name', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Security']);
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson('/api/v1/organization', ['name' => 'Acme Security Inc.'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Acme Security Inc.');

    expect($organization->fresh()->name)->toBe('Acme Security Inc.');
});

// === EDGE CASE / BUSINESS RULE ===

test('PATCH /organization attempting to set slug is rejected with 422, not silently stripped', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson('/api/v1/organization', ['name' => 'New Name', 'slug' => 'new-slug'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['slug']]]);

    expect($organization->fresh()->slug)->toBe($organization->slug);
});

test('PATCH /organization attempting to set status is rejected with 422, not silently stripped', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->patchJson('/api/v1/organization', ['name' => 'New Name', 'status' => 'SUSPENDED'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['status']]]);

    expect($organization->fresh()->status->value)->toBe('ACTIVE');
});

// === AUTHORIZATION (asymmetric — Admin/Member both rejected on PATCH) ===

test('an admin cannot update the organization', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($admin))
        ->patchJson('/api/v1/organization', ['name' => 'New Name'])
        ->assertForbidden();
});

test('a member cannot update the organization', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->patchJson('/api/v1/organization', ['name' => 'New Name'])
        ->assertForbidden();
});

test('an unauthenticated request cannot view or update the organization', function () {
    $this->getJson('/api/v1/organization')->assertUnauthorized();
    $this->patchJson('/api/v1/organization', ['name' => 'New Name'])->assertUnauthorized();
});

// === DATA ISOLATION ===

test('a user always sees their own organization, never another', function () {
    $organizationA = Organization::factory()->create(['name' => 'Org A']);
    $organizationB = Organization::factory()->create(['name' => 'Org B']);
    $ownerA = User::factory()->owner()->for($organizationA)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/organization')
        ->assertOk()
        ->assertJsonPath('data.id', $organizationA->id)
        ->assertJsonMissing(['id' => $organizationB->id]);
});
