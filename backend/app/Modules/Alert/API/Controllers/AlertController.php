<?php

namespace App\Modules\Alert\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alert\Application\AcknowledgeAlertAction;
use App\Modules\Alert\Application\GetAlertAction;
use App\Modules\Alert\Application\ListAlertsAction;
use App\Modules\Alert\Application\ResolveAlertAction;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use App\Modules\Alert\Presentation\AlertCollection;
use App\Modules\Alert\Presentation\AlertDetailResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * All four routes are JWT-only, Owner/Admin/Member equally — see
 * 04-authorization.md §2-4 and adr/ADR-003-all-roles-can-act-on-alerts.md.
 * No Role middleware anywhere in this controller: "is an authenticated
 * Human" is the entire check.
 */
class AlertController extends Controller
{
    // GET /api/v1/alerts
    public function index(Request $request, ListAlertsAction $action): AlertCollection
    {
        $alerts = $action->handle(
            organizationId: $this->organizationId($request),
            status: $this->statusFromQuery($request),
            severity: $this->severityFromQuery($request),
            perPage: $this->perPage($request),
            page: (int) $request->integer('page', 1),
        );

        return new AlertCollection($alerts);
    }

    // GET /api/v1/alerts/{alertId}
    public function show(Request $request, string $alertId, GetAlertAction $action): AlertDetailResource
    {
        return new AlertDetailResource($action->handle($this->organizationId($request), $alertId));
    }

    // PATCH /api/v1/alerts/{alertId}/acknowledge
    public function acknowledge(Request $request, string $alertId, AcknowledgeAlertAction $action): JsonResponse
    {
        $alert = $action->handle($this->organizationId($request), $alertId, $this->userId($request));

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'status' => $alert->status,
                'acknowledged_at' => $alert->acknowledged_at,
                'acknowledged_by' => $alert->acknowledged_by,
            ],
        ]);
    }

    // PATCH /api/v1/alerts/{alertId}/resolve
    public function resolve(Request $request, string $alertId, ResolveAlertAction $action): JsonResponse
    {
        $alert = $action->handle($this->organizationId($request), $alertId, $this->userId($request));

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'status' => $alert->status,
                'resolved_at' => $alert->resolved_at,
                'resolved_by' => $alert->resolved_by,
            ],
        ]);
    }

    private function organizationId(Request $request): string
    {
        return (string) $request->user('api')->organization_id;
    }

    /**
     * acknowledged_by / resolved_by are always the authenticated User's own
     * ID — never accepted from a request body. See 04-authorization.md §6.
     */
    private function userId(Request $request): string
    {
        return (string) $request->user('api')->id;
    }

    private function statusFromQuery(Request $request): ?AlertStatus
    {
        $status = $request->query('status');

        return $status ? AlertStatus::tryFrom(strtoupper($status)) : null;
    }

    private function severityFromQuery(Request $request): ?Severity
    {
        $severity = $request->query('severity');

        return $severity ? Severity::tryFrom(strtoupper($severity)) : null;
    }
}
