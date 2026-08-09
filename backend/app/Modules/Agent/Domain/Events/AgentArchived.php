<?php

namespace App\Modules\Agent\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent was archived — the Agent module has zero
 * knowledge of who, if anyone, is listening. The API Key submodule
 * (Authentication) is the one known listener today; see
 * 04-api-key-coordination.md §4.
 */
class AgentArchived
{
    use Dispatchable;

    public function __construct(
        public readonly string $agentId,
        public readonly string $organizationId,
    ) {}
}
