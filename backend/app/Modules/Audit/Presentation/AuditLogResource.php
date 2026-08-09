<?php

namespace App\Modules\Audit\Presentation;

use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches 08-api-contract.md §5-6 exactly — used for both the list item
 * shape and the single-entry detail shape (identical, unlike Agent/
 * Observation's list-vs-detail split — an audit log entry has no large
 * field like raw_ases_json to exclude from the list view).
 *
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'action' => $this->action,
            'resource_type' => $this->resource_type,
            'resource_id' => $this->resource_id,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
