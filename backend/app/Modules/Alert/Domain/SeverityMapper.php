<?php

namespace App\Modules\Alert\Domain;

/**
 * Implements ADR-001's fixed, even risk_score thresholds — an explicitly
 * flagged engineering default, not a frozen business rule. A single,
 * isolated function so recalibrating the thresholds later (or making them
 * Organization-configurable, per ADR-011) touches one place, not a spray
 * of conditionals. Pure PHP: no Laravel, no Eloquent, no HTTP concepts.
 */
class SeverityMapper
{
    public function fromRiskScore(int $riskScore): Severity
    {
        return match (true) {
            $riskScore <= 24 => Severity::Low,
            $riskScore <= 49 => Severity::Medium,
            $riskScore <= 74 => Severity::High,
            default => Severity::Critical,
        };
    }
}
