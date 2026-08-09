<?php

namespace App\Modules\Dashboard\Presentation;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Dashboard\Domain\DashboardSnapshot;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maps DashboardSnapshot to the exact JSON shape in 06-api-contract.md §1.
 * Deliberately minimal field selection per embedded list — narrower than
 * AgentResource/ObservationSummaryResource/AlertSummaryResource, matching
 * the documented response exactly rather than reusing those resources
 * wholesale (which would leak extra fields never specified for this
 * summary endpoint).
 *
 * Note on the Agent/Observation/Alert imports below: 04-aggregation-contracts.md
 * §6 forbids importing another module's Infrastructure/Domain layers —
 * that rule is about bypassing a contract (e.g. querying Agent::query()
 * directly), not about naming the type a contract's own interface already
 * declares as its return value (AgentSummaryContract literally returns
 * Agent[]). Every prior module's composition code follows the same
 * precedent — e.g. Alert's AlertDetailResource types Prediction and
 * Observation directly, since those are exactly what
 * PredictionLookupContract/ObservationLookupContract hand back.
 *
 * @mixin DashboardSnapshot
 */
class DashboardResource extends JsonResource
{
    public function __construct(private readonly DashboardSnapshot $snapshot)
    {
        parent::__construct($snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'organization_stats' => [
                'total_agents' => $this->snapshot->totalAgents,
                'active_agents' => $this->snapshot->activeAgentCount,
                'total_observations_last_30_days' => $this->snapshot->totalObservationsLast30Days,
                'open_alerts' => $this->snapshot->openAlerts,
            ],
            'active_agents' => array_map(
                fn (Agent $agent) => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'status' => $agent->status,
                    'last_seen_at' => $agent->last_seen_at,
                ],
                $this->snapshot->activeAgents,
            ),
            'recent_observations' => array_map(
                fn (Observation $observation) => [
                    'id' => $observation->id,
                    'agent_id' => $observation->agent_id,
                    'analysis_status' => $observation->analysis_status,
                    'received_at' => $observation->received_at,
                ],
                $this->snapshot->recentObservations,
            ),
            'recent_alerts' => array_map(
                // `reasons` added per RC-3 / DATAFLOW-002 / WALK-003 --
                // frontend/src/pages/Dashboard.jsx's spotlightAlert reads
                // `.reasons[0]` directly off this list. Requires `prediction`
                // to already be eager-loaded (AlertRepository::
                // listRecentForOrganization() does this) -- same N+1
                // reasoning as AlertSummaryResource.
                fn (Alert $alert) => [
                    'id' => $alert->id,
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                    'created_at' => $alert->created_at,
                    'reasons' => $alert->prediction?->prediction_json['reasons'] ?? [],
                ],
                $this->snapshot->recentAlerts,
            ),
            'risk_summary' => $this->snapshot->riskSummary,
        ];
    }
}
