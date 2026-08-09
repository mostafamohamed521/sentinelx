<?php

namespace App\Modules\Audit\Presentation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Wraps a paginated Audit Log list in the platform-wide pagination shape —
 * same pattern as every prior module's Collection (Agent, Observation,
 * Alert). Reused as-is for Security Logs — same shape, filtered data.
 */
class AuditLogCollection extends ResourceCollection
{
    public $collects = AuditLogResource::class;

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
