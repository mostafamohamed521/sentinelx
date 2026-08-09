<?php

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;
use App\Modules\Analysis\Domain\Events\PredictionStored;
use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

function dispatchPredictionStored(Prediction $prediction): void
{
    PredictionStored::dispatch($prediction->id, $prediction->observation_id, $prediction->observation->organization_id);
}

// === HAPPY PATH ===

test('a MALICIOUS prediction creates an Alert with the correct severity', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Malicious,
        'risk_score' => 88,
    ]);

    dispatchPredictionStored($prediction);

    $alert = Alert::where('prediction_id', $prediction->id)->firstOrFail();

    expect($alert->severity)->toBe(Severity::Critical)
        ->and($alert->status)->toBe(AlertStatus::Open);
});

test('a SUSPICIOUS prediction creates an Alert with the correct severity', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Suspicious,
        'risk_score' => 40,
    ]);

    dispatchPredictionStored($prediction);

    $alert = Alert::where('prediction_id', $prediction->id)->firstOrFail();

    expect($alert->severity)->toBe(Severity::Medium)
        ->and($alert->status)->toBe(AlertStatus::Open);
});

test('a successfully created Alert logs its generation (OBS-002/OBS-005)', function () {
    Log::spy();

    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Malicious,
        'risk_score' => 90,
    ]);

    dispatchPredictionStored($prediction);

    $alert = Alert::where('prediction_id', $prediction->id)->firstOrFail();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context = []) => $message === 'Alert generated.'
            && ($context['alert_id'] ?? null) === $alert->id
            && ($context['prediction_id'] ?? null) === $prediction->id
            && ($context['severity'] ?? null) === Severity::Critical->value
        );
});

// === EDGE CASE ===

test('a SAFE prediction never produces an Alert row', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Safe,
        'risk_score' => 5,
    ]);

    dispatchPredictionStored($prediction);

    expect(Alert::count())->toBe(0);
});

test('two PredictionStored events for the same prediction never produce two Alert rows', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Malicious,
        'risk_score' => 95,
    ]);

    dispatchPredictionStored($prediction);
    dispatchPredictionStored($prediction);

    expect(Alert::where('prediction_id', $prediction->id)->count())->toBe(1);
});

test('the alerts table UNIQUE constraint on prediction_id rejects a second insert directly, independent of the idempotency check', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create();
    $repository = app(AlertRepository::class);

    $repository->create([
        'prediction_id' => $prediction->id,
        'severity' => Severity::High,
        'status' => AlertStatus::Open,
    ]);

    expect(fn () => $repository->create([
        'prediction_id' => $prediction->id,
        'severity' => Severity::High,
        'status' => AlertStatus::Open,
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(Alert::where('prediction_id', $prediction->id)->count())->toBe(1);
});

// === BUSINESS RULE ===

test('severity is always derived by SeverityMapper, never accepted or guessed elsewhere', function () {
    $prediction = Prediction::factory()->for(Observation::factory())->create([
        'verdict' => Verdict::Malicious,
        'risk_score' => 74,
    ]);

    dispatchPredictionStored($prediction);

    $alert = Alert::where('prediction_id', $prediction->id)->firstOrFail();

    expect($alert->severity)->toBe(Severity::High);
});
