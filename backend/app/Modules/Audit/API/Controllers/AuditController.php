<?php

namespace App\Modules\Audit\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\API\Controllers\Concerns\ResolvesAuditListQuery;
use App\Modules\Audit\Application\GetAuditLogAction;
use App\Modules\Audit\Application\ListAuditLogsAction;
use App\Modules\Audit\Presentation\AuditLogCollection;
use App\Modules\Audit\Presentation\AuditLogResource;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ResolvesAuditListQuery;

    // GET /api/v1/audit-logs — Owner, Admin only (EnsureOwnerOrAdminRole, route-level)
    public function index(Request $request, ListAuditLogsAction $action): AuditLogCollection
    {
        $logs = $action->handle(
            organizationId: $this->organizationId($request),
            actorId: $request->query('actor_id'),
            action: $request->query('action'),
            resourceType: $request->query('resource_type'),
            from: $this->dateQuery($request, 'from'),
            to: $this->dateQuery($request, 'to'),
            perPage: $this->perPage($request),
            page: (int) $request->integer('page', 1),
        );

        return new AuditLogCollection($logs);
    }

    // GET /api/v1/audit-logs/{auditLogId} — Owner, Admin only
    public function show(Request $request, string $auditLogId, GetAuditLogAction $action): AuditLogResource
    {
        return new AuditLogResource($action->handle($this->organizationId($request), $auditLogId));
    }
}
