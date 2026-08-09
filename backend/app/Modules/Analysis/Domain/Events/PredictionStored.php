<?php

namespace App\Modules\Analysis\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that a Prediction was durably stored — the Analysis module has
 * zero knowledge of who, if anyone, is listening. The Alert module is the
 * one known listener today, evaluating whether the Prediction's verdict
 * warrants an Alert; see 05-cross-module-boundaries.md §2 in the Alert
 * module's own docs. Structurally identical to Agent's AgentArchived event.
 */
class PredictionStored
{
    use Dispatchable;

    public function __construct(
        public readonly string $predictionId,
        public readonly string $observationId,
        public readonly string $organizationId,
    ) {}
}
