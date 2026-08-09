<?php

namespace App\Modules\Analysis\Application;

use App\Modules\Analysis\Domain\Events\PredictionStored;
use App\Modules\Analysis\Domain\Exceptions\InvalidMlResponseException;
use App\Modules\Analysis\Domain\Exceptions\MLCommunicationException;
use App\Modules\Analysis\Domain\MLResponseValidator;
use App\Modules\Analysis\Infrastructure\MLClient\MLClient;
use App\Modules\Analysis\Infrastructure\Persistence\PredictionRepository;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Called by AnalyzeObservationJob for one already-claimed (PROCESSING)
 * Observation. Fetches it via Observation's own exposed contract, hands it
 * to the ML Engine unmodified, and durably records whatever comes back —
 * never deciding a verdict itself (01-overview.md §4).
 *
 * Two distinct failure kinds are handled differently, per
 * 04-ml-client-contract.md §4-5:
 *   - MLCommunicationException (transport failure) is left to propagate, so
 *     the Job's own retry/backoff can absorb a transient outage. Only once
 *     retries are exhausted does the Job's failed() hook call markFailed().
 *   - InvalidMlResponseException (contract violation) is caught here and
 *     resolved to markFailed() immediately, without retry — a malformed
 *     response is deterministic, so retrying would reproduce it identically.
 */
class AnalyzeObservationAction
{
    public function __construct(
        // Direct use of Observation's own concrete Repository, not just the
        // interface — this Action needs both the read contract method and
        // the markCompleted/markFailed write methods that live on it. Same
        // pattern already used for AgentRepository inside
        // ReceiveObservationAction (Stage 3).
        private readonly ObservationRepository $observations,
        private readonly MLClient $mlClient,
        private readonly MLResponseValidator $validator,
        private readonly PredictionRepository $predictions,
    ) {}

    /**
     * @throws MLCommunicationException
     */
    public function handle(string $observationId, string $organizationId): void
    {
        $observation = $this->observations->findByIdForOrganization($observationId, $organizationId);

        if (! $observation) {
            throw new RuntimeException("Observation {$observationId} was claimed for analysis but no longer exists.");
        }

        // Generated per analysis attempt (job execution), not threaded from
        // the original observation-submission HTTP request — analysis is
        // asynchronous and queue-dispatched, with no HTTP request context of
        // its own by the time it runs. This is the identifier that ties
        // these log lines to the ML Service's own log line for the same
        // call. See Phase 1.5 / OBS-001.
        $requestId = (string) Str::uuid();
        Log::withContext(['request_id' => $requestId, 'observation_id' => $observationId]);

        Log::info('Analysis started.', ['observation_id' => $observationId]);
        $startedAt = now();

        $response = $this->mlClient->analyze($observation, $requestId);

        try {
            $this->validator->validate($response);
        } catch (InvalidMlResponseException $e) {
            // ERROR-002: previously silent — a malformed ML response was
            // resolved to markFailed() with no trace of why. This is the
            // single most likely failure mode while Phase 2 is reworking the
            // ML contract, so it needs to be debuggable now, not deferred to
            // Phase 4's full retrofit.
            Log::warning('Analysis failed: ML response failed contract validation.', [
                'metric' => 'ml_call_failed',
                'observation_id' => $observationId,
                'reason' => $e->getMessage(),
            ]);

            $this->observations->markFailed($observationId, now());

            return;
        }

        $prediction = $this->predictions->create([
            'observation_id' => $observation->id,
            'verdict' => $response['verdict'],
            'confidence' => $response['confidence'],
            'risk_score' => $response['risk_score'],
            'summary' => $response['summary'],
            'model_version' => $response['model_version'],
            'prediction_json' => $response,
            'analyzed_at' => now(),
        ]);

        $this->observations->markCompleted($observationId, now());

        $durationMs = $startedAt->diffInMilliseconds(now());

        Log::info('Analysis completed.', [
            'metric' => 'ml_call_duration_ms',
            'value' => $durationMs,
            'observation_id' => $observationId,
            'verdict' => $response['verdict'],
            'risk_score' => $response['risk_score'],
            'duration_ms' => $durationMs,
        ]);

        // The one new line this Sprint adds to Analysis — see
        // docs/backend/alert/05-cross-module-boundaries.md §2. Analysis has
        // zero reference to the Alert module and zero knowledge that
        // anything listens.
        PredictionStored::dispatch($prediction->id, $observation->id, $organizationId);
    }
}
