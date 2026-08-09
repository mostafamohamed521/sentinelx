<?php

namespace App\Modules\Alert\Domain;

use App\Modules\Alert\Domain\Exceptions\AlertAlreadyAcknowledgedException;
use App\Modules\Alert\Domain\Exceptions\AlertAlreadyResolvedException;

/**
 * Pure business-rule checks for the Alert state machine — no Eloquent, no
 * HTTP. See 02-domain.md §5 (state machine) and §8 (invariant 4: status
 * only ever moves forward, never backward, in V1).
 */
class AlertPolicy
{
    /**
     * @throws AlertAlreadyAcknowledgedException
     */
    public function ensureCanBeAcknowledged(AlertStatus $currentStatus): void
    {
        if ($currentStatus !== AlertStatus::Open) {
            throw new AlertAlreadyAcknowledgedException;
        }
    }

    /**
     * Callable from either OPEN or ACKNOWLEDGED — skip-ahead is allowed, no
     * business rule requires acknowledging first. See
     * 03-generation-pipeline.md §4.
     *
     * @throws AlertAlreadyResolvedException
     */
    public function ensureCanBeResolved(AlertStatus $currentStatus): void
    {
        if ($currentStatus === AlertStatus::Resolved) {
            throw new AlertAlreadyResolvedException;
        }
    }
}
