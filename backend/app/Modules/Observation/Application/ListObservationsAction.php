<?php

namespace App\Modules\Observation\Application;

use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListObservationsAction
{
    public function __construct(
        private readonly ObservationRepository $observations,
    ) {}

    public function handle(
        string $organizationId,
        ?string $agentId,
        ?AnalysisStatus $status,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->observations->listForOrganization($organizationId, $agentId, $status, $perPage, $page);
    }
}
