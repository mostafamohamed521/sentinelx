<?php

namespace App\Modules\Analysis\Domain;

use App\Modules\Analysis\Domain\Exceptions\InvalidMlResponseException;

/**
 * Confirms the ML Engine's response is genuinely usable BEFORE any write is
 * attempted — see 04-ml-client-contract.md §4 and 08-implementation-roadmap.md
 * §2. Pure PHP: no Laravel, no Eloquent, no HTTP concepts.
 *
 * Checks structural presence and documented ranges only. It never judges
 * whether a verdict is "correct" — that's the one rule this module never
 * breaks (01-overview.md §4).
 */
class MLResponseValidator
{
    /**
     * @param  array<string, mixed>  $response
     *
     * @throws InvalidMlResponseException
     */
    public function validate(array $response): void
    {
        $verdict = $response['verdict'] ?? null;

        if (! is_string($verdict) || Verdict::tryFrom($verdict) === null) {
            throw new InvalidMlResponseException('ML response verdict is missing or not one of SAFE|SUSPICIOUS|MALICIOUS.');
        }

        $riskScore = $response['risk_score'] ?? null;

        if (! is_int($riskScore) || $riskScore < 0 || $riskScore > 100) {
            throw new InvalidMlResponseException('ML response risk_score is missing or outside the 0-100 range.');
        }

        $confidence = $response['confidence'] ?? null;

        if (! is_numeric($confidence) || (float) $confidence < 0 || (float) $confidence > 1) {
            throw new InvalidMlResponseException('ML response confidence is missing or outside the 0-1 range.');
        }

        if (! $this->isNonEmptyString($response['summary'] ?? null)) {
            throw new InvalidMlResponseException('ML response summary is missing.');
        }

        if (! $this->isNonEmptyString($response['model_version'] ?? null)) {
            throw new InvalidMlResponseException('ML response model_version is missing.');
        }
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
