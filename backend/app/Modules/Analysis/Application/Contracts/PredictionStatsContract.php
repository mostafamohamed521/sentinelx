<?php

namespace App\Modules\Analysis\Application\Contracts;

/**
 * The aggregate read surface Dashboard (Stage 6) consumes for its
 * `risk_summary` field — grouped by verdict, not by Alert's severity, per
 * docs/backend/dashboard/adr/ADR-002-risk-summary-by-verdict-not-severity.md.
 * Covers every analyzed Observation, SAFE included.
 */
interface PredictionStatsContract
{
    /**
     * @return array{SAFE: int, SUSPICIOUS: int, MALICIOUS: int}
     */
    public function verdictDistributionForOrganization(string $organizationId): array;
}
