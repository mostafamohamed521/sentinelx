<?php

namespace App\Modules\Observation\Presentation;

use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List view — GET /observations and GET /agents/{agentId}/observations.
 * Deliberately excludes raw_ases_json (can be large; a list view has no
 * need for it — see 07-api-contract.md §2) and organization_id (every
 * response is already implicitly scoped to the caller's Organization).
 *
 * @mixin Observation
 */
class ObservationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'agent_id' => $this->agent_id,
            'analysis_status' => $this->analysis_status,
            'received_at' => $this->received_at,
            'created_at' => $this->created_at,
        ];
    }
}
