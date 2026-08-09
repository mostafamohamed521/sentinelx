<?php

use App\Modules\Organization\Domain\OrganizationStatus;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

// === HAPPY PATH ===

test('an organization can be created with a unique slug and defaults to ACTIVE', function () {
    $organization = Organization::factory()->create(['slug' => 'acme-corp']);

    expect($organization->id)->toBeString()
        ->and($organization->slug)->toBe('acme-corp')
        ->and($organization->status)->toBe(OrganizationStatus::Active);

    $this->assertDatabaseHas('organizations', [
        'id' => $organization->id,
        'slug' => 'acme-corp',
        'status' => 'ACTIVE',
    ]);
});

test('status is cast to the OrganizationStatus enum', function () {
    $organization = Organization::factory()->suspended()->create();

    expect($organization->status)->toBe(OrganizationStatus::Suspended);
});

// === CONSTRAINTS ===

test('two organizations may share the same name', function () {
    Organization::factory()->create(['name' => 'Duplicate Inc']);
    $second = Organization::factory()->create(['name' => 'Duplicate Inc']);

    expect($second->exists)->toBeTrue();
});

test('slug must be unique', function () {
    Organization::factory()->create(['slug' => 'unique-slug']);

    expect(fn () => Organization::factory()->create(['slug' => 'unique-slug']))
        ->toThrow(QueryException::class);
});

// === RELATIONSHIPS ===

test('an organization has many users, agents, and observations', function () {
    $organization = Organization::factory()->create();

    expect($organization->users())->toBeInstanceOf(HasMany::class)
        ->and($organization->agents())->toBeInstanceOf(HasMany::class)
        ->and($organization->observations())->toBeInstanceOf(HasMany::class);
});
