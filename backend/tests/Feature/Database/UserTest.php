<?php

use App\Modules\Authentication\Identity\Domain\UserRole;
use App\Modules\Authentication\Identity\Domain\UserStatus;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

// === HAPPY PATH ===

test('a user belongs to an organization and defaults to MEMBER/ACTIVE', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->for($organization)->create();

    expect($user->organization->is($organization))->toBeTrue()
        ->and($user->role)->toBe(UserRole::Member)
        ->and($user->status)->toBe(UserStatus::Active);
});

test('password_hash is hidden from array/JSON serialization', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKey('password_hash');
});

test('authentication reads the password from password_hash', function () {
    $user = User::factory()->create(['password_hash' => 'a-hashed-value']);

    expect($user->getAuthPassword())->toBe('a-hashed-value');
});

// === CONSTRAINTS ===

test('email is unique across the whole platform, not just per organization', function () {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => User::factory()->create(['email' => 'duplicate@example.com']))
        ->toThrow(QueryException::class);
});

test('a user cannot be created without an organization_id', function () {
    expect(fn () => User::factory()->create(['organization_id' => null]))
        ->toThrow(QueryException::class);
});

// === RELATIONSHIPS ===

test('a user belongs to exactly one organization', function () {
    $user = User::factory()->create();

    expect($user->organization())->toBeInstanceOf(BelongsTo::class);
});
