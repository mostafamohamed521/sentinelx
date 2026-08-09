<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Hash;

// === HAPPY PATH ===

test('a verified, active user can log in and receives a bearer token', function () {
    $user = User::factory()->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
        ->assertJsonPath('token_type', 'bearer');
});

test('login updates last_login_at', function () {
    $user = User::factory()->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
        'last_login_at' => null,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
    ])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

// === FAILURE CASES — all must return the exact same generic 401 shape ===

test('login fails with a generic message for a wrong password', function () {
    User::factory()->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'wrong-password',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('login fails with a generic message for an unknown email', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@acme.example',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('login fails with a generic message for a disabled user', function () {
    User::factory()->disabled()->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('login fails with a generic message for a user whose Organization is suspended (STATE-002)', function () {
    $organization = Organization::factory()->suspended()->create();

    User::factory()->for($organization)->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('login fails with a generic message for an unverified user', function () {
    User::factory()->unverified()->create([
        'email' => 'ahmed@acme.example',
        'password_hash' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'ahmed@acme.example',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED')
        ->assertJsonPath('error.message', 'Authentication failed.')
        ->assertJsonStructure(['error' => ['request_id']]);
});

test('login is rate limited', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@acme.example',
            'password' => 'password123',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@acme.example',
        'password' => 'password123',
    ]);

    $response->assertStatus(429);
});
