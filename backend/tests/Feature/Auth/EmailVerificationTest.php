<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

// === HAPPY PATH ===

test('following a valid signed verification link marks the email as verified', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $response = $this->getJson($url);

    $response->assertOk();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('visiting an already-consumed verification link twice is idempotent', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertOk()->assertJsonPath('message', 'Email already verified.');
});

test('a verified user can log in', function () {
    $user = User::factory()->unverified()->create([
        'password_hash' => Hash::make('password123'),
    ]);

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);
    $this->getJson($url)->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertOk();
});

test('an authenticated unverified user can request the verification link be resent', function () {
    $user = User::factory()->unverified()->create();
    $token = JWTAuth::fromUser($user);

    Notification::fake();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/resend')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});

// === FAILURE / EDGE CASES ===

test('an expired verification link is rejected', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->subMinutes(1), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->getJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('a verification link with a tampered hash is rejected', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->getJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resend does nothing but confirm when the user is already verified', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/resend')
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified.');
});
