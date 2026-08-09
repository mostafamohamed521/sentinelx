<?php

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Infrastructure\Persistence\AuditRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ListAuditLogsAction
{
    public function __construct(
        private readonly AuditRepository $audits,
    ) {}

    public function handle(
        string $organizationId,
        ?string $actorId,
        ?string $action,
        ?string $resourceType,
        ?Carbon $from,
        ?Carbon $to,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return $this->audits->listForOrganization(
            organizationId: $organizationId,
            actorId: $actorId,
            action: $action,
            resourceType: $resourceType,
            from: $from,
            to: $to,
            actionIn: null,
            perPage: $perPage,
            page: $page,
        );
    }
}
