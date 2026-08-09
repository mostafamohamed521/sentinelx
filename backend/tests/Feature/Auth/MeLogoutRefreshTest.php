<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

// === HAPPY PATH ===

test('me returns the authenticated user for a valid token', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me');

    $response->assertOk()->assertJsonPath('data.id', $user->id);
});

test('me never exposes password_hash', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertJsonMissing(['password_hash']);
});

test('refresh returns a new bearer token', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/refresh');

    $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in']);
});

test('logout succeeds for an authenticated user', function () {
    $user = User::factory()->create();
    $token = JWTAuth::fromUser($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();
});

// === FAILURE CASES ===

test('protected auth endpoints reject requests with no token', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

test('protected auth endpoints reject an invalid token', function () {
    $this->withHeader('Authorization', 'Bearer not-a-real-token')
        ->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});
