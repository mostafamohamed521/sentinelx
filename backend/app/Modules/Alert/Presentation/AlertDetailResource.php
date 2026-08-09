<?php

namespace App\Modules\Alert\Presentation;

use App\Modules\Alert\Application\AlertDetail;
use App\Modules\Analysis\Presentation\PredictionDetailResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single detail view — GET /alerts/{id}. Composes the Alert with its
 * related Prediction and a minimal Observation embed. See
 * 06-api-contract.md §2.
 *
 * The Prediction embed uses PredictionDetailResource, not
 * PredictionSummaryResource (RC-3 / DATAFLOW-002 / WALK-003) — this is the
 * one confirmed place the Frontend needs the fuller `evidence` field
 * (frontend/src/pages/AlertDetails.jsx's Evidence tab), in addition to the
 * `reasons` field every Prediction embed now carries.
 *
 * @mixin AlertDetail
 */
class AlertDetailResource extends JsonResource
{
    public function __construct(private readonly AlertDetail $detail)
    {
        parent::__construct($detail);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->detail->alert;
        $observation = $this->detail->observation;

        return [
            'id' => $alert->id,
            'severity' => $alert->severity,
            'status' => $alert->status,
            'acknowledged_at' => $alert->acknowledged_at,
            'acknowledged_by' => $alert->acknowledged_by,
            'resolved_at' => $alert->resolved_at,
            'resolved_by' => $alert->resolved_by,
            'created_at' => $alert->created_at,
            'updated_at' => $alert->updated_at,
            'prediction' => new PredictionDetailResource($this->detail->prediction),
            'observation' => [
                'id' => $observation->id,
                'agent_id' => $observation->agent_id,
                'received_at' => $observation->received_at,
            ],
        ];
    }
}
