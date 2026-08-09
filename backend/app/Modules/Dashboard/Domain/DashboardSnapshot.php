<?php

namespace App\Modules\Dashboard\Domain;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Observation\Infrastructure\Persistence\Observation;

/**
 * A plain, non-persisted value object — not an Eloquent model, not backed
 * by any table. Replaces the "entity" every other module's Domain layer
 * starts from; this module owns no data, per 01-overview.md §4 and
 * 02-domain.md §2. Has no invariants beyond "each field is populated from
 * exactly one source contract" — see 04-aggregation-contracts.md.
 */
final readonly class DashboardSnapshot
{
    /**
     * @param  array<int, Agent>  $activeAgents
     * @param  array<int, Observation>  $recentObservations
     * @param  array<int, Alert>  $recentAlerts
     * @param  array{SAFE: int, SUSPICIOUS: int, MALICIOUS: int}  $riskSummary
     */
    public function __construct(
        public int $totalAgents,
        public int $activeAgentCount,
        public int $totalObservationsLast30Days,
        public int $openAlerts,
        public array $activeAgents,
        public array $recentObservations,
        public array $recentAlerts,
        public array $riskSummary,
    ) {}
}
