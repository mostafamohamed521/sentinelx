<?php

use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;

// === HAPPY PATH ===

test('claiming a batch selects the oldest PENDING observations and flips them to PROCESSING', function () {
    $older = Observation::factory()->create(['received_at' => now()->subMinutes(10)]);
    $newer = Observation::factory()->create(['received_at' => now()->subMinutes(1)]);

    $claimed = app(ObservationRepository::class)->claimNextPendingBatch(10);

    expect($claimed)->toHaveCount(2)
        ->and($claimed[0]->id)->toBe($older->id)
        ->and($claimed[1]->id)->toBe($newer->id);

    expect(Observation::findOrFail($older->id)->analysis_status)->toBe(AnalysisStatus::Processing)
        ->and(Observation::findOrFail($older->id)->processing_started_at)->not->toBeNull();
});

test('claiming respects the given limit', function () {
    Observation::factory(5)->create();

    $claimed = app(ObservationRepository::class)->claimNextPendingBatch(2);

    expect($claimed)->toHaveCount(2)
        ->and(Observation::where('analysis_status', AnalysisStatus::Pending)->count())->toBe(3);
});

// === EDGE CASE ===

test('two sequential claim calls never both claim the same observation', function () {
    Observation::factory(3)->create();

    $repository = app(ObservationRepository::class);

    $first = $repository->claimNextPendingBatch(10);
    $second = $repository->claimNextPendingBatch(10);

    expect($first)->toHaveCount(3)
        ->and($second)->toHaveCount(0);

    $firstIds = collect($first)->pluck('id')->all();
    $secondIds = collect($second)->pluck('id')->all();

    expect(array_intersect($firstIds, $secondIds))->toBeEmpty();
});

test('claiming with no PENDING observations returns an empty array', function () {
    Observation::factory()->completed()->create();

    $claimed = app(ObservationRepository::class)->claimNextPendingBatch(10);

    expect($claimed)->toBe([]);
});

// === BUSINESS RULE ===

test('a FAILED observation is never claimed again', function () {
    $observation = Observation::factory()->create();
    app(ObservationRepository::class)->markFailed($observation->id, now());

    $claimed = app(ObservationRepository::class)->claimNextPendingBatch(10);

    expect($claimed)->toBe([]);
});

test('a PROCESSING or COMPLETED observation is never re-claimed', function () {
    $processing = Observation::factory()->create();
    app(ObservationRepository::class)->markProcessing($processing->id);
    $completed = Observation::factory()->completed()->create();

    $claimed = app(ObservationRepository::class)->claimNextPendingBatch(10);

    expect($claimed)->toBe([]);
});
