<?php

namespace App\Modules\Alert\Application;

use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAlertsAction
{
    public function __construct(
        private readonly AlertRepository $alerts,
    ) {}

    public function handle(
        string $organizationId,
        ?AlertStatus $status,
        ?Severity $severity,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->alerts->listForOrganization($organizationId, $status, $severity, $perPage, $page);
    }
}
