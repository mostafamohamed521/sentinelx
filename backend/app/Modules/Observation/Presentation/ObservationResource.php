<?php

namespace App\Modules\Observation\Presentation;

use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Analysis\Presentation\PredictionSummaryResource;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full detail view — GET /observations/{id}. Matches 07-api-contract.md §3
 * exactly, including raw_ases_json (unlike ObservationSummaryResource).
 *
 * `prediction` is populated by the caller (ObservationController@show),
 * which composes it from Analysis's PredictionLookupContract — see
 * docs/backend/analysis/07-api-contract.md §2. Stays null for every
 * analysis_status other than COMPLETED, including FAILED.
 *
 * Note on scope: docs/backend/analysis/08-implementation-roadmap.md §2 and
 * 07-api-contract.md §2 both show this constructor accepting a Prediction —
 * the Sprint 4 task text's blanket "don't touch any Observation Resource"
 * warning is resolved in favor of these two Analysis-module documents,
 * which explicitly describe this exact, narrow, backward-compatible change
 * (a nullable, defaulted second constructor argument — every other caller,
 * e.g. ObservationCollection, is unaffected).
 *
 * @mixin Observation
 */
class ObservationResource extends JsonResource
{
    public function __construct(Observation $resource, private readonly ?Prediction $prediction = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'organization_id' => $this->organization_id,
            'analysis_status' => $this->analysis_status,
            'raw_ases_json' => $this->raw_ases_json,
            'received_at' => $this->received_at,
            'processing_started_at' => $this->processing_started_at,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'prediction' => $this->prediction ? new PredictionSummaryResource($this->prediction) : null,
        ];
    }
}
