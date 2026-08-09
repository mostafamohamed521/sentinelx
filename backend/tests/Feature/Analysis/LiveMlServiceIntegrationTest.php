<?php

use App\Modules\Analysis\Application\AnalyzeObservationAction;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2's Principle-3 verification: makes a genuine HTTP call to a real,
 * locally-running ML Service (uvicorn api:app), not Http::fake(). Per the
 * resolution plan itself, an Http::fake()-passing test is not evidence this
 * boundary actually works -- CONTRACT-001/002/003/004 were all invisible to
 * the existing fake-based suite because a fake, by construction, cannot
 * detect a wrong path, a wrong envelope shape, or a vocabulary the ML
 * Service structurally cannot produce.
 *
 * Run locally with (matching ML_SERVICE_TOKEN on both sides -- phpunit.xml
 * sets it to "testing-ml-service-token" for the test environment):
 *   cd ml && ML_SERVICE_TOKEN=testing-ml-service-token uvicorn api:app --port 8001
 *
 * Skips gracefully (not a failure) if that isn't running -- this file was
 * authored and is intended to run in an environment with the ml/ dataset and
 * model artifacts present; see the Phase 2 report for why this session's own
 * environment could not execute it (ml/'s dataset/model files are not part
 * of this repository).
 */
beforeEach(function () {
    try {
        $health = Http::timeout(2)->get(rtrim(config('services.ml_engine.url'), '/').'/health');
    } catch (Throwable) {
        $health = null;
    }

    if (! $health || ! $health->successful()) {
        $this->markTestSkipped(
            'Live ML Service not reachable at '.config('services.ml_engine.url').
            ' -- start it with `cd ml && ML_SERVICE_TOKEN=testing-ml-service-token uvicorn api:app --port 8001` to run this test.'
        );
    }
});

/**
 * Deterministic regardless of the trained classifier/embedding artifacts:
 * an empty events array never reaches either model, so risk_score is always
 * the pipeline's fixed no-signal baseline (10) -- safely inside the SAFE
 * band under any threshold calibration.
 */
function liveAsesPayload(array $events): array
{
    return [
        'context' => [
            'framework' => 'CrewAI',
            'agent_version' => '1.2.0',
            'environment' => 'production',
        ],
        'events' => $events,
    ];
}

function sensitivePathFileAccessEvent(): array
{
    // Matched by taxonomy_matcher.py's own explicit, dataset-independent rule
    // pre-check (`SENSITIVE_PATH_PATTERNS`) at full confidence (1.0) -- not
    // the embedding matcher, so this is deterministic even though the real
    // taxonomy CSVs/model artifacts are what a live ML Service would load.
    return [
        'header' => ['event_type' => 'file_access', 'timestamp' => '2026-07-29T09:59:52Z'],
        'payload' => ['resource' => '/etc/shadow', 'operation' => 'read', 'details' => ['path' => '/etc/shadow', 'access_mode' => 'read']],
    ];
}

test('a SAFE-range Observation (no events) round-trips through a live ML Service into a stored SAFE Prediction', function () {
    $observation = Observation::factory()->create(['raw_ases_json' => liveAsesPayload([])]);

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();
    $prediction = Prediction::where('observation_id', $observation->id)->first();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($prediction)->not->toBeNull()
        ->and($prediction->verdict->value)->toBe('SAFE')
        ->and($prediction->risk_score)->toBeLessThan(50);
});

test('a SUSPICIOUS-range Observation (two sensitive-path reads) round-trips through a live ML Service into a stored SUSPICIOUS Prediction', function () {
    $observation = Observation::factory()->create([
        'raw_ases_json' => liveAsesPayload([sensitivePathFileAccessEvent(), sensitivePathFileAccessEvent()]),
    ]);

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();
    $prediction = Prediction::where('observation_id', $observation->id)->first();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($prediction)->not->toBeNull()
        ->and($prediction->verdict->value)->toBe('SUSPICIOUS')
        ->and($prediction->risk_score)->toBeGreaterThanOrEqual(50)->toBeLessThan(85);
});

test('a MALICIOUS-range Observation (three sensitive-path reads) round-trips through a live ML Service into a stored MALICIOUS Prediction', function () {
    $observation = Observation::factory()->create([
        'raw_ases_json' => liveAsesPayload([sensitivePathFileAccessEvent(), sensitivePathFileAccessEvent(), sensitivePathFileAccessEvent()]),
    ]);

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();
    $prediction = Prediction::where('observation_id', $observation->id)->first();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($prediction)->not->toBeNull()
        ->and($prediction->verdict->value)->toBe('MALICIOUS')
        ->and($prediction->risk_score)->toBeGreaterThanOrEqual(85);
});

test('a real analysis produces matching request_id log lines on the Backend side', function () {
    Log::spy();

    $observation = Observation::factory()->create(['raw_ases_json' => liveAsesPayload([])]);

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    // Confirms Phase 1.5's logging groundwork actually fires around a real
    // (not faked) ML call. Cross-checking that the ML Service's own stdout
    // line carries the *same* request_id is not asserted here -- capturing
    // another process's stdout from inside a Pest test is disproportionate
    // machinery for this phase; see the Phase 2 report for how that half was
    // verified manually instead.
    Log::shouldHaveReceived('info')->with('Analysis started.', Mockery::on(fn ($ctx) => isset($ctx['observation_id'])));
    Log::shouldHaveReceived('info')->with('Analysis completed.', Mockery::on(fn ($ctx) => isset($ctx['observation_id'], $ctx['verdict'], $ctx['risk_score'])));
});
