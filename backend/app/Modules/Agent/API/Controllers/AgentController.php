<?php

namespace App\Modules\Agent\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agent\API\Requests\StoreAgentRequest;
use App\Modules\Agent\API\Requests\UpdateAgentRequest;
use App\Modules\Agent\Application\ArchiveAgentAction;
use App\Modules\Agent\Application\CreateAgentAction;
use App\Modules\Agent\Application\GetAgentAction;
use App\Modules\Agent\Application\ListAgentsAction;
use App\Modules\Agent\Application\UpdateAgentAction;
use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Presentation\AgentCollection;
use App\Modules\Agent\Presentation\AgentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    // GET /api/agent/me
    public function me(Request $request): AgentResource
    {
        return new AgentResource($request->user('agent'));
    }

    // GET /api/v1/agents
    public function index(Request $request, ListAgentsAction $action): AgentCollection
    {
        $status = $this->statusFromQuery($request);

        $agents = $action->handle(
            organizationId: $this->organizationId($request),
            status: $status,
            perPage: $this->perPage($request),
            page: (int) $request->integer('page', 1),
        );

        return new AgentCollection($agents);
    }

    // POST /api/v1/agents
    public function store(StoreAgentRequest $request, CreateAgentAction $action): JsonResponse
    {
        $agent = $action->handle(
            $this->organizationId($request),
            $request->validated(),
            (string) $request->user('api')->id,
        );

        return (new AgentResource($agent))->response()->setStatusCode(201);
    }

    // GET /api/v1/agents/{agentId}
    public function show(Request $request, string $agentId, GetAgentAction $action): AgentResource
    {
        return new AgentResource($action->handle($this->organizationId($request), $agentId));
    }

    // PATCH /api/v1/agents/{agentId}
    public function update(UpdateAgentRequest $request, string $agentId, UpdateAgentAction $action): AgentResource
    {
        $agent = $action->handle(
            $this->organizationId($request),
            $agentId,
            $request->validated(),
            (string) $request->user('api')->id,
        );

        return new AgentResource($agent);
    }

    // PATCH /api/v1/agents/{agentId}/archive
    public function archive(Request $request, string $agentId, ArchiveAgentAction $action): JsonResponse
    {
        $agent = $action->handle($this->organizationId($request), $agentId);

        return response()->json([
            'data' => [
                'id' => $agent->id,
                'status' => $agent->status,
                'updated_at' => $agent->updated_at,
            ],
        ]);
    }

    /**
     * organization_id is never accepted from a request parameter — always
     * derived from the authenticated Human's JWT. See 03-lifecycle.md §2.
     */
    private function organizationId(Request $request): string
    {
        return (string) $request->user('api')->organization_id;
    }

    private function statusFromQuery(Request $request): ?AgentStatus
    {
        $status = $request->query('status');

        if (! $status) {
            return null;
        }

        return AgentStatus::tryFrom(strtoupper($status));
    }
}
