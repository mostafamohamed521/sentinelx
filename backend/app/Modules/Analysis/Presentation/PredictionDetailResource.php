<?php

namespace App\Modules\Analysis\Presentation;

use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `prediction` field embedded inside GET /alerts/{id} — see
 * AlertDetailResource. Everything PredictionSummaryResource has, plus
 * `evidence`: the fuller, structured, per-event findings (evidence_type,
 * description, reference, confidence) the ML Service produces alongside
 * `reasons` (RC-3 / DATAFLOW-002 / WALK-003).
 *
 * Not used anywhere PredictionSummaryResource is — Alert Detail's own
 * Evidence tab (frontend/src/pages/AlertDetails.jsx's "evidence" tab) is
 * the one confirmed place this fuller data is actually consumed; every
 * other Prediction embed (ObservationResource, AlertSummaryResource's own
 * `reasons`-only addition) stays on the lighter Resource by design.
 *
 * @mixin Prediction
 */
class PredictionDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'verdict' => $this->verdict,
            'confidence' => $this->confidence,
            'risk_score' => $this->risk_score,
            'summary' => $this->summary,
            'model_version' => $this->model_version,
            'analyzed_at' => $this->analyzed_at,
            'reasons' => $this->prediction_json['reasons'] ?? [],
            'evidence' => $this->prediction_json['evidence'] ?? [],
        ];
    }
}
