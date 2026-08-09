<?php

namespace App\Modules\Observation\Application\Contracts;

use App\Modules\Observation\Infrastructure\Persistence\Observation;

/**
 * The read-only surface Analysis (Stage 4) will consume to compose
 * GET /observations/{id}'s eventual `prediction` field — parallel to
 * Agent's AgentLookupContract. Exposed now, consumed by nobody yet — see
 * 05-cross-module-boundaries.md §3 and adr/ADR-003-prediction-composition-deferred.md.
 */
interface ObservationLookupContract
{
    public function findByIdForOrganization(string $observationId, string $organizationId): ?Observation;
}
