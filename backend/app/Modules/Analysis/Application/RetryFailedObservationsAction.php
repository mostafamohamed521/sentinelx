<?php

namespace App\Modules\Analysis\Application;

use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Support\Facades\Log;

/**
 * STATE-004/FAILURE-003: the operator-triggered recovery path for a
 * terminal FAILED Observation — previously no code path anywhere,
 * automated or documented-manual, ever brought one back. Deliberately not
 * a public API endpoint: no Role/authorization model was ever designed for
 * "who can force a re-analysis," so this is CLI/operator-only, invoked via
 * RetryFailedObservationsCommand.
 */
class RetryFailedObservationsAction
{
    public function __construct(
        // Same direct-concrete-repository cross-module pattern already
        // used by ClaimPendingObservationsAction above it.
        private readonly ObservationRepository $observations,
    ) {}

    public function handle(?string $observationId): int
    {
        $retried = $observationId !== null
            ? ($this->observations->retryFailed($observationId) ? 1 : 0)
            : $this->observations->retryAllFailed();

        Log::info('FAILED Observation(s) manually requeued for re-analysis.', [
            'metric' => 'observations_manually_retried',
            'value' => $retried,
            'observation_id' => $observationId,
        ]);

        return $retried;
    }
}
