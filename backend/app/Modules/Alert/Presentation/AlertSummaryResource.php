<?php

namespace App\Modules\Alert\Presentation;

use App\Modules\Alert\Infrastructure\Persistence\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List view — GET /alerts. Deliberately minimal, excluding the related
 * Observation/Prediction detail — same list-vs-detail pattern already used
 * in Agent and Observation. See 06-api-contract.md §1.
 *
 * `reasons` is the one exception (RC-3 / DATAFLOW-002): the human-readable
 * justification strings are small enough that including them here doesn't
 * meaningfully violate this Resource's own "stay minimal" design, and
 * frontend/src/pages/AgentDetails.jsx's Related-Alerts list (itself backed
 * by this same GET /alerts endpoint, filtered by agent_id) reads
 * `a.reasons[0]` directly off this list — there is no detail-view fetch on
 * that page to source it from instead. The fuller, structured `evidence`
 * field is NOT added here — see PredictionDetailResource, used only by
 * AlertDetailResource, for that.
 *
 * Requires the `prediction` relation to already be eager-loaded by the
 * caller (AlertRepository::listForOrganization() does this) — accessing it
 * here without that would be an N+1 query, once per Alert in the page.
 *
 * @mixin Alert
 */
class AlertSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prediction_id' => $this->prediction_id,
            'severity' => $this->severity,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'reasons' => $this->prediction?->prediction_json['reasons'] ?? [],
        ];
    }
}
