<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Facades\Hash;

// === HAPPY PATH ===

test('any role can update their own full_name', function (?string $factoryState) {
    $humanFactory = User::factory();
    $human = ($factoryState ? $humanFactory->{$factoryState}() : $humanFactory)->create(['full_name' => 'Ahmed']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($human))
        ->patchJson('/api/v1/me', ['full_name' => 'Ahmed Updated'])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Ahmed Updated');

    expect($human->fresh()->full_name)->toBe('Ahmed Updated');
})->with([
    'owner' => 'owner',
    'admin' => 'admin',
    'member' => null,
]);

test('any role can change their own password', function () {
    $user = User::factory()->create(['password_hash' => Hash::make('old-password-123')]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/me/change-password', [
            'current_password' => 'old-password-123',
            'new_password' => 'new-password-456',
            'new_password_confirmation' => 'new-password-456',
        ])
        ->assertOk()
        ->assertJson(['status' => 'success', 'message' => 'Password changed successfully']);

    expect(Hash::check('new-password-456', $user->fresh()->password_hash))->toBeTrue();
});

// === EDGE CASE ===

test('change-password with an incorrect current_password returns 401 and updates nothing', function () {
    $user = User::factory()->create(['password_hash' => Hash::make('old-password-123')]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/me/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-456',
            'new_password_confirmation' => 'new-password-456',
        ])
        ->assertUnauthorized();

    expect(Hash::check('old-password-123', $user->fresh()->password_hash))->toBeTrue();
});

test('change-password with mismatched confirmation returns 422', function () {
    $user = User::factory()->create(['password_hash' => Hash::make('old-password-123')]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/me/change-password', [
            'current_password' => 'old-password-123',
            'new_password' => 'new-password-456',
            'new_password_confirmation' => 'does-not-match',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['new_password']]]);
});

test('PATCH /me attempting to set email or role has no effect', function () {
    $user = User::factory()->create(['email' => 'original@acme.example']);
    $originalRole = $user->role;

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->patchJson('/api/v1/me', ['full_name' => 'New Name', 'email' => 'new@acme.example', 'role' => 'OWNER'])
        ->assertOk();

    expect($user->fresh()->email)->toBe('original@acme.example')
        ->and($user->fresh()->role)->toBe($originalRole);
});

// === AUTHORIZATION ===

test('an unauthenticated request cannot update the profile or change the password', function () {
    $this->patchJson('/api/v1/me', ['full_name' => 'New Name'])->assertUnauthorized();
    $this->postJson('/api/v1/me/change-password', [
        'current_password' => 'x',
        'new_password' => 'new-password-456',
        'new_password_confirmation' => 'new-password-456',
    ])->assertUnauthorized();
});

// === DATA ISOLATION ===

test('there is no route parameter allowing a user to update another users profile', function () {
    $userA = User::factory()->create(['full_name' => 'User A']);
    $userB = User::factory()->create(['full_name' => 'User B']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($userA))
        ->patchJson('/api/v1/me', ['full_name' => 'Hijacked'])
        ->assertOk();

    expect($userA->fresh()->full_name)->toBe('Hijacked')
        ->and($userB->fresh()->full_name)->toBe('User B');
});
