<?php

namespace App\Modules\Observation\API\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analysis\Application\Contracts\PredictionLookupContract;
use App\Modules\Observation\API\Requests\SubmitObservationRequest;
use App\Modules\Observation\Application\GetObservationAction;
use App\Modules\Observation\Application\ListObservationsAction;
use App\Modules\Observation\Application\ReceiveObservationAction;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Presentation\ObservationAcceptedResource;
use App\Modules\Observation\Presentation\ObservationCollection;
use App\Modules\Observation\Presentation\ObservationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObservationController extends Controller
{
    // POST /api/v1/observations — API Key (Agent) guard only
    public function store(SubmitObservationRequest $request, ReceiveObservationAction $action): JsonResponse
    {
        $agent = $request->user('agent');

        $observation = $action->handle(
            agentId: (string) $agent->id,
            organizationId: (string) $agent->organization_id,
            rawBody: $request->getContent(),
        );

        // 202, not 201/200 — a deliberate, already-decided contract choice.
        // See adr/ADR-001-async-ingestion-202-accepted.md.
        return (new ObservationAcceptedResource($observation))
            ->response()
            ->setStatusCode(202);
    }

    // GET /api/v1/observations — JWT (Human) guard, any Role
    public function index(Request $request, ListObservationsAction $action): ObservationCollection
    {
        $observations = $action->handle(
            organizationId: $this->organizationId($request),
            agentId: $request->query('agent_id'),
            status: $this->statusFromQuery($request),
            perPage: $this->perPage($request),
            page: (int) $request->integer('page', 1),
        );

        return new ObservationCollection($observations);
    }

    // GET /api/v1/observations/{observationId} — JWT (Human) guard, any Role
    public function show(
        Request $request,
        string $observationId,
        GetObservationAction $action,
        PredictionLookupContract $predictions,
    ): ObservationResource {
        $observation = $action->handle($this->organizationId($request), $observationId);

        // The one composition line Stage 4 adds here — see
        // docs/backend/analysis/07-api-contract.md §2.
        $prediction = $predictions->findByObservationId($observation->id);

        return new ObservationResource($observation, $prediction);
    }

    private function organizationId(Request $request): string
    {
        return (string) $request->user('api')->organization_id;
    }

    private function statusFromQuery(Request $request): ?AnalysisStatus
    {
        $status = $request->query('analysis_status');

        if (! $status) {
            return null;
        }

        return AnalysisStatus::tryFrom(strtoupper($status));
    }
}
