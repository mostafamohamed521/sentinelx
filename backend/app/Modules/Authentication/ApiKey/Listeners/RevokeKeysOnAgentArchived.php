<?php

namespace App\Modules\Authentication\ApiKey\Listeners;

use App\Modules\Agent\Domain\Events\AgentArchived;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;

/**
 * Reacts to the Agent module's AgentArchived event — the Agent module
 * never calls this listener, never imports it, never knows it exists.
 * See 04-api-key-coordination.md §4.
 */
class RevokeKeysOnAgentArchived
{
    public function handle(AgentArchived $event): void
    {
        ApiKey::query()
            ->where('agent_id', $event->agentId)
            ->where('status', ApiKeyStatus::Active)
            ->update(['status' => ApiKeyStatus::Revoked]);
    }
}
