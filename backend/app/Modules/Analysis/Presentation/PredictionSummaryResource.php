<?php

namespace App\Modules\Analysis\Presentation;

use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `prediction` field embedded inside GET /observations/{id} — see
 * 07-api-contract.md §1. Exposes the five promoted columns plus
 * id/analyzed_at, plus `reasons` (RC-3 / DATAFLOW-002) — the human-readable
 * justification strings, small enough not to meaningfully violate this
 * Resource's original "stay minimal" design, and needed everywhere this
 * Resource is already embedded (also AlertDetailResource, prior to RC-3;
 * see PredictionDetailResource for where that embed now points instead).
 *
 * Still deliberately excludes the fuller `evidence` field and the rest of
 * prediction_json — see PredictionDetailResource, used only by
 * AlertDetailResource's Evidence-tab need, for that.
 *
 * @mixin Prediction
 */
class PredictionSummaryResource extends JsonResource
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
        ];
    }
}
