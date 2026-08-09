<?php

namespace App\Modules\Analysis\Infrastructure\Persistence;

use App\Modules\Analysis\Application\Contracts\PredictionLookupContract;
use App\Modules\Analysis\Application\Contracts\PredictionStatsContract;
use App\Modules\Analysis\Domain\Verdict;

/**
 * The only place inside the Analysis module that touches the `predictions`
 * table directly. A Prediction is written exactly once, ever, per
 * Observation (02-domain.md §5) — there is no update()/delete() method here,
 * deliberately.
 */
class PredictionRepository implements PredictionLookupContract, PredictionStatsContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Prediction
    {
        return Prediction::create($attributes);
    }

    public function findByObservationId(string $observationId): ?Prediction
    {
        return Prediction::query()
            ->where('observation_id', $observationId)
            ->first();
    }

    public function findById(string $predictionId): ?Prediction
    {
        return Prediction::find($predictionId);
    }

    /**
     * predictions has no organization_id column of its own — scoped via a
     * structural join through Observation, the same explicitly-sanctioned
     * pattern already used by Alert's AlertRepository::scopedToOrganization().
     *
     * @return array{SAFE: int, SUSPICIOUS: int, MALICIOUS: int}
     */
    public function verdictDistributionForOrganization(string $organizationId): array
    {
        $counts = Prediction::query()
            ->whereHas('observation', fn ($query) => $query->where('organization_id', $organizationId))
            ->selectRaw('verdict, count(*) as aggregate')
            ->groupBy('verdict')
            ->pluck('aggregate', 'verdict');

        return [
            'SAFE' => (int) ($counts[Verdict::Safe->value] ?? 0),
            'SUSPICIOUS' => (int) ($counts[Verdict::Suspicious->value] ?? 0),
            'MALICIOUS' => (int) ($counts[Verdict::Malicious->value] ?? 0),
        ];
    }
}
