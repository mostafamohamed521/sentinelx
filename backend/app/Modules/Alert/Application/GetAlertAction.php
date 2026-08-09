<?php

namespace App\Modules\Alert\Application;

use App\Modules\Alert\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;
use App\Modules\Analysis\Application\Contracts\PredictionLookupContract;
use App\Modules\Observation\Application\Contracts\ObservationLookupContract;
use RuntimeException;

/**
 * Composes an Alert with its related Prediction and Observation — a normal,
 * permitted read since Alert -> Analysis -> Observation is entirely
 * downward through the dependency chain (unlike the deferred composition
 * pattern seen in Stage 3/4). See 06-api-contract.md §2.
 */
class GetAlertAction
{
    public function __construct(
        private readonly AlertRepository $alerts,
        private readonly PredictionLookupContract $predictions,
        private readonly ObservationLookupContract $observations,
    ) {}

    /**
     * @throws AlertNotFoundException
     */
    public function handle(string $organizationId, string $alertId): AlertDetail
    {
        $alert = $this->alerts->findById($alertId, $organizationId);

        if (! $alert) {
            throw new AlertNotFoundException;
        }

        $prediction = $this->predictions->findById($alert->prediction_id);

        if (! $prediction) {
            throw new RuntimeException("Alert {$alertId} references Prediction {$alert->prediction_id}, which no longer exists.");
        }

        $observation = $this->observations->findByIdForOrganization($prediction->observation_id, $organizationId);

        if (! $observation) {
            throw new RuntimeException("Alert {$alertId} references Observation {$prediction->observation_id}, which no longer exists.");
        }

        return new AlertDetail($alert, $prediction, $observation);
    }
}
