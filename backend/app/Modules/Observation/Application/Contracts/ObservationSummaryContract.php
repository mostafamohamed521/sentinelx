<?php

namespace App\Modules\Observation\Application\Contracts;

use App\Modules\Observation\Infrastructure\Persistence\Observation;
use DateTimeInterface;

/**
 * The aggregate, multi-record read surface Dashboard (Stage 6) consumes —
 * a different shape than ObservationLookupContract's single-record lookup.
 * See docs/backend/dashboard/04-aggregation-contracts.md §3.
 */
interface ObservationSummaryContract
{
    public function countForOrganizationSince(string $organizationId, DateTimeInterface $since): int;

    /**
     * Ordered by received_at DESC.
     *
     * @return array<int, Observation>
     */
    public function listRecentForOrganization(string $organizationId, int $limit): array;
}
