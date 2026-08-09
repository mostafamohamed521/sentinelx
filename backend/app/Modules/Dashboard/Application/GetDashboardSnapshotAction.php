<?php

namespace App\Modules\Dashboard\Application;

use App\Modules\Agent\Application\Contracts\AgentSummaryContract;
use App\Modules\Alert\Application\Contracts\AlertSummaryContract;
use App\Modules\Analysis\Application\Contracts\PredictionStatsContract;
use App\Modules\Dashboard\Domain\DashboardSnapshot;
use App\Modules\Observation\Application\Contracts\ObservationSummaryContract;

/**
 * Calls all four contracts and assembles DashboardSnapshot — no business
 * logic of its own beyond assembly, per 01-overview.md §4's golden rule.
 * Never queries agents/observations/predictions/alerts directly.
 */
class GetDashboardSnapshotAction
{
    /**
     * A small, fixed limit for a dashboard summary widget, not a
     * substitute for the full paginated list endpoints — freely
     * adjustable, not a frozen business rule. See 06-api-contract.md §1.
     */
    private const RECENT_ITEMS_LIMIT = 5;

    /**
     * "Recent" observations for the 30-day count means the trailing 30
     * days from now — not a frozen business rule either, but the one
     * number DASHBOARD_API.md's example response actually names
     * (total_observations_last_30_days).
     */
    private const OBSERVATION_WINDOW_DAYS = 30;

    public function __construct(
        private readonly AgentSummaryContract $agents,
        private readonly ObservationSummaryContract $observations,
        private readonly PredictionStatsContract $predictions,
        private readonly AlertSummaryContract $alerts,
    ) {}

    public function handle(string $organizationId): DashboardSnapshot
    {
        $alertCounts = $this->alerts->countByStatusForOrganization($organizationId);

        return new DashboardSnapshot(
            totalAgents: $this->agents->countTotalForOrganization($organizationId),
            activeAgentCount: $this->agents->countActiveForOrganization($organizationId),
            totalObservationsLast30Days: $this->observations->countForOrganizationSince(
                $organizationId,
                now()->subDays(self::OBSERVATION_WINDOW_DAYS),
            ),
            openAlerts: $alertCounts['OPEN'],
            activeAgents: $this->agents->listRecentlyActiveForOrganization($organizationId, self::RECENT_ITEMS_LIMIT),
            recentObservations: $this->observations->listRecentForOrganization($organizationId, self::RECENT_ITEMS_LIMIT),
            recentAlerts: $this->alerts->listRecentForOrganization($organizationId, self::RECENT_ITEMS_LIMIT),
            riskSummary: $this->predictions->verdictDistributionForOrganization($organizationId),
        );
    }
}
