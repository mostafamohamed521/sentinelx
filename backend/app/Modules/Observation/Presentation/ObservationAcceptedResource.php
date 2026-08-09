<?php

namespace App\Modules\Observation\Presentation;

use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * POST /observations response — deliberately minimal. The SDK's only
 * remaining responsibility is knowing the submission succeeded; it must
 * never receive any analysis result synchronously, since none exists yet.
 * See 03-ingestion-pipeline.md §7.
 *
 * @mixin Observation
 */
class ObservationAcceptedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'received_at' => $this->received_at,
            'analysis_status' => $this->analysis_status,
        ];
    }
}
