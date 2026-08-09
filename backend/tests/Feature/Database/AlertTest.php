<?php

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;

// === HAPPY PATH ===

test('an alert belongs to exactly one prediction and defaults to OPEN', function () {
    $alert = Alert::factory()->create();

    expect($alert->status)->toBe(AlertStatus::Open)
        ->and($alert->prediction)->toBeInstanceOf(Prediction::class);
});

test('an alert can be acknowledged and resolved', function () {
    $acknowledged = Alert::factory()->acknowledged()->create();
    $resolved = Alert::factory()->resolved()->create();

    expect($acknowledged->status)->toBe(AlertStatus::Acknowledged)
        ->and($acknowledged->acknowledged_at)->not->toBeNull()
        ->and($resolved->status)->toBe(AlertStatus::Resolved)
        ->and($resolved->resolved_at)->not->toBeNull();
});

test('severity is cast to the Severity enum', function () {
    $alert = Alert::factory()->create(['severity' => Severity::Critical]);

    expect($alert->severity)->toBe(Severity::Critical);
});

// === CONSTRAINTS ===

test('a prediction can produce at most one alert', function () {
    $prediction = Prediction::factory()->malicious()->create();
    Alert::factory()->for($prediction)->create();

    expect(fn () => Alert::factory()->for($prediction)->create())
        ->toThrow(QueryException::class);
});

// === RELATIONSHIPS ===

test('an alert belongs to exactly one prediction', function () {
    $alert = Alert::factory()->create();

    expect($alert->prediction())->toBeInstanceOf(BelongsTo::class);
});
