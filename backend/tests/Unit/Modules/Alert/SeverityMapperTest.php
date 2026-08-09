<?php

use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Domain\SeverityMapper;

// === HAPPY PATH ===

test('a risk_score comfortably inside each band maps to the expected severity', function (int $riskScore, Severity $expected) {
    expect((new SeverityMapper)->fromRiskScore($riskScore))->toBe($expected);
})->with([
    'well inside LOW' => [10, Severity::Low],
    'well inside MEDIUM' => [35, Severity::Medium],
    'well inside HIGH' => [60, Severity::High],
    'well inside CRITICAL' => [90, Severity::Critical],
]);

// === EDGE CASE (boundary values — the single most likely bug in this Sprint) ===

test('risk_score boundaries map to the correct severity on both sides of every threshold', function (int $riskScore, Severity $expected) {
    expect((new SeverityMapper)->fromRiskScore($riskScore))->toBe($expected);
})->with([
    'lower bound 0 -> LOW' => [0, Severity::Low],
    '24 -> LOW' => [24, Severity::Low],
    '25 -> MEDIUM' => [25, Severity::Medium],
    '49 -> MEDIUM' => [49, Severity::Medium],
    '50 -> HIGH' => [50, Severity::High],
    '74 -> HIGH' => [74, Severity::High],
    '75 -> CRITICAL' => [75, Severity::Critical],
    'upper bound 100 -> CRITICAL' => [100, Severity::Critical],
]);
