<?php

namespace App\Modules\Agent\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent was created — the Agent module has zero
 * knowledge of who, if anyone, is listening. Audit is the one known
 * listener today. Structurally identical to AgentArchived (Stage 2).
 */
class AgentCreated
{
    use Dispatchable;

    public function __construct(
        public readonly string $agentId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
        public readonly string $agentName,
    ) {}
}
