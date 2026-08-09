<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

// === HAPPY PATH ===

test('an api key belongs to an agent and defaults to ACTIVE', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->status)->toBe(ApiKeyStatus::Active)
        ->and($apiKey->agent)->toBeInstanceOf(Agent::class);
});

test('key_hash is hidden from array/JSON serialization', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->toArray())->not->toHaveKey('key_hash');
});

// === CONSTRAINTS ===

test('key_hash must be unique', function () {
    ApiKey::factory()->create(['key_hash' => 'duplicate-hash']);

    expect(fn () => ApiKey::factory()->create(['key_hash' => 'duplicate-hash']))
        ->toThrow(QueryException::class);
});

// === BUSINESS RULE: single ACTIVE key per agent (ADR-004) ===

test('activating a new key automatically revokes the agent\'s previous ACTIVE key', function () {
    $agent = Agent::factory()->create();
    $original = ApiKey::factory()->for($agent)->create();

    expect($original->fresh()->status)->toBe(ApiKeyStatus::Active);

    $rotated = ApiKey::factory()->for($agent)->create();

    expect($original->fresh()->status)->toBe(ApiKeyStatus::Revoked)
        ->and($rotated->fresh()->status)->toBe(ApiKeyStatus::Active);
});

test('creating a REVOKED key does not disturb the agent\'s existing ACTIVE key', function () {
    $agent = Agent::factory()->create();
    $active = ApiKey::factory()->for($agent)->create();

    ApiKey::factory()->for($agent)->revoked()->create();

    expect($active->fresh()->status)->toBe(ApiKeyStatus::Active);
});

test('rotation only revokes keys belonging to the same agent', function () {
    $agentA = Agent::factory()->create();
    $agentB = Agent::factory()->create();

    $keyA = ApiKey::factory()->for($agentA)->create();
    ApiKey::factory()->for($agentB)->create();

    expect($keyA->fresh()->status)->toBe(ApiKeyStatus::Active);
});

// === RELATIONSHIPS ===

test('an api key belongs to exactly one agent', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->agent())->toBeInstanceOf(BelongsTo::class);
});
