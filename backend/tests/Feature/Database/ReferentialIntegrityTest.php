<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\QueryException;

// Every foreign key in the schema uses ON DELETE RESTRICT — no CASCADE,
// no SET NULL. See backend/docs/database/02-schema/relationships.md.

test('an organization cannot be deleted while it has users', function () {
    $user = User::factory()->create();

    expect(fn () => $user->organization->delete())->toThrow(QueryException::class);
});

test('an organization cannot be deleted while it has agents', function () {
    $agent = Agent::factory()->create();

    expect(fn () => $agent->organization->delete())->toThrow(QueryException::class);
});

test('an agent cannot be deleted while it has api keys', function () {
    $apiKey = ApiKey::factory()->create();

    expect(fn () => $apiKey->agent->delete())->toThrow(QueryException::class);
});

test('an agent cannot be deleted while it has observations', function () {
    $observation = Observation::factory()->create();

    expect(fn () => $observation->agent->delete())->toThrow(QueryException::class);
});

test('an observation cannot be deleted while it has a prediction', function () {
    $prediction = Prediction::factory()->create();

    expect(fn () => $prediction->observation->delete())->toThrow(QueryException::class);
});

test('a prediction cannot be deleted while it has an alert', function () {
    $alert = Alert::factory()->create();

    expect(fn () => $alert->prediction->delete())->toThrow(QueryException::class);
});

test('the full dependency chain can be built end to end', function () {
    $organization = Organization::factory()->create();
    $agent = Agent::factory()->for($organization)->create();
    $apiKey = ApiKey::factory()->for($agent)->create();
    $observation = Observation::factory()->for($agent)->for($organization)->completed()->create();
    $prediction = Prediction::factory()->malicious()->for($observation)->create();
    $alert = Alert::factory()->for($prediction)->create();

    expect($agent->organization->is($organization))->toBeTrue()
        ->and($apiKey->agent->is($agent))->toBeTrue()
        ->and($observation->agent->is($agent))->toBeTrue()
        ->and($observation->organization->is($organization))->toBeTrue()
        ->and($prediction->observation->is($observation))->toBeTrue()
        ->and($alert->prediction->is($prediction))->toBeTrue();
});
