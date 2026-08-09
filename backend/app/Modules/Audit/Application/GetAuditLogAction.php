<?php

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Domain\Exceptions\AuditLogNotFoundException;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Audit\Infrastructure\Persistence\AuditRepository;

class GetAuditLogAction
{
    public function __construct(
        private readonly AuditRepository $audits,
    ) {}

    /**
     * @throws AuditLogNotFoundException
     */
    public function handle(string $organizationId, string $auditLogId): AuditLog
    {
        $auditLog = $this->audits->findById($auditLogId, $organizationId);

        if (! $auditLog) {
            throw new AuditLogNotFoundException;
        }

        return $auditLog;
    }
}
