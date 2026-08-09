<?php

namespace App\Modules\Alert\Presentation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a paginated Alert list in the platform-wide pagination shape — see
 * docs/09-api-reference/08-PAGINATION.md. Same pattern as Agent's
 * AgentCollection and Observation's ObservationCollection.
 */
class AlertCollection extends ResourceCollection
{
    public $collects = AlertSummaryResource::class;

    /**
     * @return array<int, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'pagination' => [
                'page' => $this->resource->currentPage(),
                'per_page' => $this->resource->perPage(),
                'total_items' => $this->resource->total(),
                'total_pages' => $this->resource->lastPage(),
            ],
        ];
    }
}
