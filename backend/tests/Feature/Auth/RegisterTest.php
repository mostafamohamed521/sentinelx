<?php

use App\Modules\Authentication\Identity\Domain\UserRole;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

// === HAPPY PATH ===

test('registering creates an organization and an owner user, and sends a verification email', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Ahmed Owner',
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated();

    $user = User::where('email', 'ahmed@acme.example')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Owner)
        ->and($user->email_verified_at)->toBeNull();

    $organization = Organization::where('name', 'Acme Security')->first();
    expect($organization)->not->toBeNull()
        ->and($user->organization_id)->toBe($organization->id);

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration response never exposes password_hash', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Ahmed Owner',
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertCreated()->assertJsonMissing(['password_hash']);
});

// === VALIDATION / EDGE CASES ===

test('registration fails when the email is already taken', function () {
    User::factory()->create(['email' => 'taken@acme.example']);

    $response = $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Ahmed Owner',
        'email' => 'taken@acme.example',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['email']]]);
});

test('registration fails when the password confirmation does not match', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Ahmed Owner',
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
        'password_confirmation' => 'not-the-same',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['details' => ['password']]]);
});

test('two organizations get distinct slugs even with the same name', function () {
    $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Ahmed Owner',
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $this->postJson('/api/v1/auth/register', [
        'organization_name' => 'Acme Security',
        'full_name' => 'Mohamed Owner',
        'email' => 'mohamed@acme2.example',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertCreated();

    $slugs = Organization::where('name', 'Acme Security')->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});
