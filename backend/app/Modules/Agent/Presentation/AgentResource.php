<?php

namespace App\Modules\Agent\Presentation;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agent
 */
class AgentResource extends JsonResource
{
    /**
     * Matches 06-api-contract.md exactly — never includes any API Key
     * field (there are none on this entity anyway) and never includes
     * organization_id (every response is already implicitly scoped to the
     * caller's Organization).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'framework' => $this->framework,
            'framework_version' => $this->framework_version,
            'description' => $this->description,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
