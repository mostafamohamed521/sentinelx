<?php

namespace App\Modules\Agent\Application\Contracts;

use App\Modules\Agent\Infrastructure\Persistence\Agent;

/**
 * The aggregate, multi-record read surface Dashboard (Stage 6) consumes —
 * a different shape than AgentLookupContract's single-record lookup, built
 * for a different consumer with a different need. See
 * docs/backend/dashboard/04-aggregation-contracts.md §2.
 */
interface AgentSummaryContract
{
    public function countTotalForOrganization(string $organizationId): int;

    public function countActiveForOrganization(string $organizationId): int;

    /**
     * Ordered by last_seen_at DESC, ACTIVE agents only — an agent that has
     * never sent an Observation (last_seen_at is null) is excluded, since
     * it cannot meaningfully be "recently active."
     *
     * @return array<int, Agent>
     */
    public function listRecentlyActiveForOrganization(string $organizationId, int $limit): array;
}
