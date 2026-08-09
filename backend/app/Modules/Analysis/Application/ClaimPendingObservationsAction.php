<?php

namespace App\Modules\Analysis\Application;

use App\Modules\Analysis\Infrastructure\Queue\AnalyzeObservationJob;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Support\Facades\Log;

/**
 * Called by PollPendingObservationsCommand. Claims a batch of PENDING
 * Observations (atomically flipped to PROCESSING as part of the claim
 * itself — see ObservationRepository::claimNextPendingBatch()) and
 * dispatches one Queue Job per claimed Observation. See
 * 03-processing-pipeline.md §3-4.
 */
class ClaimPendingObservationsAction
{
    public function __construct(
        // Direct use of Observation's own concrete Repository — the exact
        // same cross-module pattern as touchLastSeen() and
        // AnalyzeObservationAction above, applied a third time.
        private readonly ObservationRepository $observations,
    ) {}

    public function handle(int $limit): int
    {
        $claimed = $this->observations->claimNextPendingBatch($limit);

        foreach ($claimed as $observation) {
            AnalyzeObservationJob::dispatch($observation->id, $observation->organization_id);
        }

        // OBS-003: the audit's own named minimum ("Observations claimed per
        // poll cycle") as a structured, greppable log entry rather than a
        // dedicated metrics library — no frozen document mandates a
        // specific tool, and standing one up is out of this phase's scope.
        Log::info('Poll cycle claimed Observations for analysis.', [
            'metric' => 'observations_claimed_per_poll',
            'value' => count($claimed),
        ]);

        return count($claimed);
    }
}
