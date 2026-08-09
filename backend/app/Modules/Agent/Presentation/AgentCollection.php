<?php

namespace App\Modules\Agent\Presentation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a paginated Agent list in the platform-wide pagination shape —
 * see docs/09-api-reference/08-PAGINATION.md. Metadata stays separated
 * from business data, per that document's own stated design principle.
 */
class AgentCollection extends ResourceCollection
{
    public $collects = AgentResource::class;

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
