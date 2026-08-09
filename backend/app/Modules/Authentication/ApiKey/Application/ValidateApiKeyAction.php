<?php

namespace App\Modules\Authentication\ApiKey\Application;

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Organization\Domain\OrganizationStatus;

class ValidateApiKeyAction
{
    /**
     * Implements the API Key verification contract exactly as specified in
     * contracts/api-key-format.md §3: extract -> hash -> look up an ACTIVE
     * key -> resolve the Agent -> resolve the Organization (via the Agent
     * relationship). Returns null (authentication fails) unless every step
     * succeeds, including the Agent itself being ACTIVE (not ARCHIVED) -
     * see contracts/auth-errors.md.
     */
    public function handle(?string $rawKey): ?Agent
    {
        if (! $rawKey) {
            return null;
        }

        $apiKey = ApiKey::query()
            ->where('key_hash', hash('sha256', $rawKey))
            ->where('status', ApiKeyStatus::Active)
            ->first();

        if (! $apiKey || ($apiKey->expires_at && $apiKey->expires_at->isPast())) {
            return null;
        }

        $agent = $apiKey->agent;

        if (! $agent || $agent->status !== AgentStatus::Active) {
            return null;
        }

        // STATE-002: mirrors LoginUserAction's own Organization-suspended
        // check — a suspended Organization loses API access too, not just
        // Human login.
        if ($agent->organization?->status !== OrganizationStatus::Active) {
            return null;
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $agent->forceFill(['last_seen_at' => now()])->save();

        return $agent;
    }
}
