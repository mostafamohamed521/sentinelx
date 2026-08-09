<?php

namespace App\Modules\Observation\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observation\Application\ListAgentObservationsAction;
use App\Modules\Observation\Presentation\ObservationCollection;
use Illuminate\Http\Request;

class AgentObservationController extends Controller
{
    // GET /api/v1/agents/{agentId}/observations — JWT (Human) guard, any Role
    public function index(Request $request, string $agentId, ListAgentObservationsAction $action): ObservationCollection
    {
        $observations = $action->handle(
            organizationId: (string) $request->user('api')->organization_id,
            agentId: $agentId,
            perPage: $this->perPage($request),
            page: (int) $request->integer('page', 1),
        );

        return new ObservationCollection($observations);
    }
}
