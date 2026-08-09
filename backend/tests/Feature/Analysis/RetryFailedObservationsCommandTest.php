<?php

use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;

// === HAPPY PATH ===

test('a specific FAILED Observation is requeued to PENDING', function () {
    $observation = Observation::factory()->create([
        'analysis_status' => AnalysisStatus::Failed,
        'processing_started_at' => now()->subMinute(),
        'processed_at' => now(),
    ]);

    $this->artisan('analysis:retry-failed', ['observationId' => $observation->id])
        ->expectsOutput('Requeued 1 Observation(s) for re-analysis.')
        ->assertSuccessful();

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Pending)
        ->and($observation->processing_started_at)->toBeNull()
        ->and($observation->processed_at)->toBeNull();
});

test('--all requeues every currently FAILED Observation, platform-wide', function () {
    Observation::factory(3)->create(['analysis_status' => AnalysisStatus::Failed]);
    Observation::factory()->completed()->create();
    Observation::factory()->create(['analysis_status' => AnalysisStatus::Pending]);

    $this->artisan('analysis:retry-failed', ['--all' => true])
        ->expectsOutput('Requeued 3 Observation(s) for re-analysis.')
        ->assertSuccessful();

    expect(Observation::where('analysis_status', AnalysisStatus::Pending)->count())->toBe(4)
        ->and(Observation::where('analysis_status', AnalysisStatus::Failed)->count())->toBe(0);
});

// === EDGE CASE ===

test('requeuing an Observation that is not actually FAILED is a safe no-op', function () {
    $observation = Observation::factory()->completed()->create();

    $this->artisan('analysis:retry-failed', ['observationId' => $observation->id])
        ->expectsOutput('Requeued 0 Observation(s) for re-analysis.')
        ->assertSuccessful();

    expect($observation->fresh()->analysis_status)->toBe(AnalysisStatus::Completed);
});

test('neither an Observation ID nor --all is provided: the command fails loudly rather than guessing', function () {
    $this->artisan('analysis:retry-failed')
        ->assertFailed();
});
