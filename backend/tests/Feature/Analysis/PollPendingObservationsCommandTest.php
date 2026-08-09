<?php

use App\Modules\Analysis\Infrastructure\Queue\AnalyzeObservationJob;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

// === HAPPY PATH ===

test('the poller claims PENDING observations and dispatches one job per claimed observation', function () {
    Bus::fake();

    $observations = Observation::factory(3)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    Bus::assertDispatchedTimes(AnalyzeObservationJob::class, 3);

    foreach ($observations as $observation) {
        expect($observation->fresh()->analysis_status)->toBe(AnalysisStatus::Processing);
    }
});

test('the poller logs the number of Observations claimed, tagged as a metric (OBS-003)', function () {
    Bus::fake();
    Log::spy();

    Observation::factory(3)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context = []) => ($context['metric'] ?? null) === 'observations_claimed_per_poll'
            && ($context['value'] ?? null) === 3
        );
});

// === EDGE CASE ===

test('the poller respects the --limit option', function () {
    Bus::fake();

    Observation::factory(5)->create();

    $this->artisan('analysis:poll-pending-observations', ['--limit' => 2])->assertSuccessful();

    Bus::assertDispatchedTimes(AnalyzeObservationJob::class, 2);
});

test('the poller falls back to the configurable global capacity ceiling when --limit is omitted (PERF-002)', function () {
    Bus::fake();

    config(['analysis.poll_batch_limit' => 3]);
    Observation::factory(5)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    Bus::assertDispatchedTimes(AnalyzeObservationJob::class, 3);
});

test('the poller dispatches nothing when there is no PENDING work', function () {
    Bus::fake();

    Observation::factory()->completed()->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    Bus::assertNotDispatched(AnalyzeObservationJob::class);
});
