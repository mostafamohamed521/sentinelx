<?php

use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// === HAPPY PATH ===

test('a prediction belongs to exactly one observation and stores prediction_json as an array', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->observation)->toBeInstanceOf(Observation::class)
        ->and($prediction->prediction_json)->toBeArray()
        ->and($prediction->verdict)->toBeInstanceOf(Verdict::class);
});

// === CONSTRAINTS ===

test('an observation can have at most one prediction', function () {
    $observation = Observation::factory()->create();
    Prediction::factory()->for($observation)->create();

    expect(fn () => Prediction::factory()->for($observation)->create())
        ->toThrow(QueryException::class);
});

test('risk_score is constrained to 0-100 at the database level', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('CHECK constraints are only enforced on PostgreSQL — see 2026_07_27_000006_create_predictions_table.');
    }

    expect(fn () => Prediction::factory()->create(['risk_score' => 101]))
        ->toThrow(QueryException::class);
});

test('confidence is constrained to 0-1 at the database level', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        test()->markTestSkipped('CHECK constraints are only enforced on PostgreSQL — see 2026_07_27_000006_create_predictions_table.');
    }

    expect(fn () => Prediction::factory()->create(['confidence' => 1.5]))
        ->toThrow(QueryException::class);
});

// === RELATIONSHIPS ===

test('a prediction may have an alert', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->alert())->toBeInstanceOf(HasOne::class)
        ->and($prediction->alert)->toBeNull();
});
