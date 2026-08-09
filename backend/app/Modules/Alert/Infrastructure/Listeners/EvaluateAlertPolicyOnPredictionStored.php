<?php

namespace App\Modules\Alert\Infrastructure\Listeners;

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\SeverityMapper;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;
use App\Modules\Analysis\Application\Contracts\PredictionLookupContract;
use App\Modules\Analysis\Domain\Events\PredictionStored;
use App\Modules\Analysis\Domain\Verdict;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Reacts to the Analysis module's PredictionStored event — Analysis never
 * calls this listener, never imports it, never knows it exists. Implements
 * the fixed V1 generation policy from 03-generation-pipeline.md §3:
 * verdict != SAFE => create an Alert, severity via SeverityMapper.
 *
 * Event-driven rather than polling — no index exists for "Predictions not
 * yet evaluated," unlike Analysis's own PENDING-Observation poll. See
 * 03-generation-pipeline.md §1.
 */
class EvaluateAlertPolicyOnPredictionStored
{
    public function __construct(
        private readonly PredictionLookupContract $predictions,
        private readonly AlertRepository $alerts,
        private readonly SeverityMapper $severityMapper,
    ) {}

    public function handle(PredictionStored $event): void
    {
        $prediction = $this->predictions->findById($event->predictionId);

        if (! $prediction) {
            // Should not happen — the event only fires after a successful
            // Prediction write — but defensively handled per
            // 03-generation-pipeline.md §5. Logged, not thrown: this
            // listener must never crash the event dispatch.
            Log::warning('EvaluateAlertPolicyOnPredictionStored: Prediction not found.', [
                'prediction_id' => $event->predictionId,
            ]);

            return;
        }

        if ($prediction->verdict === Verdict::Safe) {
            return;
        }

        // Idempotency guard: checked before the INSERT to avoid a needless
        // failed write; the UNIQUE constraint on prediction_id is the final
        // guarantee for the race case (two events for the same Prediction).
        if ($this->alerts->findByPredictionId($prediction->id)) {
            return;
        }

        $severity = $this->severityMapper->fromRiskScore($prediction->risk_score);

        try {
            $alert = $this->alerts->create([
                'prediction_id' => $prediction->id,
                'severity' => $severity,
                'status' => AlertStatus::Open,
            ]);

            // OBS-002/OBS-005: the last uncovered item from the audit's own
            // "auth -> observation -> analysis -> alert" checklist. Distinct
            // from the defensive Log::warning above -- this is the routine
            // success case, not an unreachable-in-practice guard.
            Log::info('Alert generated.', [
                'alert_id' => $alert->id,
                'prediction_id' => $prediction->id,
                'severity' => $severity->value,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Lost the race against another dispatch of the same event —
            // an Alert already exists for this Prediction. Not an error.
        }
    }
}
