<?php

namespace App\Modules\Agent\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent's details were updated — the Agent module has
 * zero knowledge of who, if anyone, is listening.
 */
class AgentUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $agentId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
