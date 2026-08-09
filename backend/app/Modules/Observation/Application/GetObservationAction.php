<?php

namespace App\Modules\Observation\Application;

use App\Modules\Observation\Domain\Exceptions\ObservationNotFoundException;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;

class GetObservationAction
{
    public function __construct(
        private readonly ObservationRepository $observations,
    ) {}

    /**
     * @throws ObservationNotFoundException
     */
    public function handle(string $organizationId, string $observationId): Observation
    {
        $observation = $this->observations->findById($observationId, $organizationId);

        if (! $observation) {
            throw new ObservationNotFoundException;
        }

        return $observation;
    }
}
