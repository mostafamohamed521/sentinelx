<?php

namespace App\Modules\Alert\Application\Contracts;

use App\Modules\Alert\Infrastructure\Persistence\Alert;

/**
 * The read-only surface Dashboard consumes — exposed since Stage 5, exactly
 * as ObservationLookupContract and PredictionLookupContract were before
 * their consumers existed. See 05-cross-module-boundaries.md §3. Returns
 * raw counts/records only — this module never formats data for
 * widget/chart display; that's Dashboard's job.
 *
 * listRecentForOrganization() was added in Stage 6 for GET /dashboard's
 * `recent_alerts` field — see
 * docs/backend/dashboard/04-aggregation-contracts.md §5. No new interface
 * was created for this — extending the existing one is the whole payoff of
 * having exposed it a Sprint ahead of need.
 */
interface AlertSummaryContract
{
    /**
     * @return array{OPEN: int, ACKNOWLEDGED: int, RESOLVED: int}
     */
    public function countByStatusForOrganization(string $organizationId): array;

    /**
     * Ordered by created_at DESC.
     *
     * @return array<int, Alert>
     */
    public function listRecentForOrganization(string $organizationId, int $limit): array;
}
