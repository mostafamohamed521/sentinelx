<?php

use App\Modules\Analysis\Application\AnalyzeObservationAction;
use App\Modules\Analysis\Domain\Exceptions\MLCommunicationException;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Analysis\Infrastructure\Queue\AnalyzeObservationJob;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// === HAPPY PATH ===

test('the job delegates to AnalyzeObservationAction and completes without error', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'SAFE',
        'risk_score' => 5,
        'confidence' => 0.95,
        'summary' => 'Nothing unusual.',
        'model_version' => 'sentinelx-ml-1.4.2',
    ])]);

    $observation = Observation::factory()->create();

    (new AnalyzeObservationJob($observation->id, $observation->organization_id))->handle(app(AnalyzeObservationAction::class));

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and(Prediction::where('observation_id', $observation->id)->exists())->toBeTrue();
});

// === BUSINESS RULE ===

test('the job is configured with 3 tries and a growing backoff schedule', function () {
    $job = new AnalyzeObservationJob('any-id', 'any-org-id');

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60, 300]);
});

// === EDGE CASE ===

test('once retries are exhausted, the failed() hook marks the observation FAILED, never leaving it stuck PROCESSING', function () {
    $observation = Observation::factory()->create();
    app(ObservationRepository::class)->markProcessing($observation->id);

    $job = new AnalyzeObservationJob($observation->id, $observation->organization_id);
    $job->failed(new MLCommunicationException('ML Engine unreachable.'));

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Failed)
        ->and(Prediction::where('observation_id', $observation->id)->exists())->toBeFalse();
});

test('once retries are exhausted, the failed() hook logs the failure with the ml_call_failed metric tag (ERROR-005/OBS-003)', function () {
    Log::spy();

    $observation = Observation::factory()->create();
    app(ObservationRepository::class)->markProcessing($observation->id);

    $job = new AnalyzeObservationJob($observation->id, $observation->organization_id);
    $job->failed(new MLCommunicationException('ML Engine unreachable.'));

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context = []) => ($context['metric'] ?? null) === 'ml_call_failed'
            && ($context['observation_id'] ?? null) === $observation->id
        );
});
