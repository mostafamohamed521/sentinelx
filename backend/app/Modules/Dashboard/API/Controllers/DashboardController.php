<?php

namespace App\Modules\Dashboard\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Application\GetDashboardSnapshotAction;
use App\Modules\Dashboard\Presentation\DashboardResource;
use Illuminate\Http\Request;

/**
 * No Role gate beyond "is an authenticated Human" — Owner, Admin, and
 * Member all have identical access, per 05-authorization.md and
 * adr/ADR-003-all-roles-can-view-dashboard.md.
 */
class DashboardController extends Controller
{
    // GET /api/v1/dashboard
    public function show(Request $request, GetDashboardSnapshotAction $action): DashboardResource
    {
        $organizationId = (string) $request->user('api')->organization_id;

        return new DashboardResource($action->handle($organizationId));
    }
}
