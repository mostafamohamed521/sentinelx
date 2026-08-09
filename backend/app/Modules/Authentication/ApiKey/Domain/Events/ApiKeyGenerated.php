<?php

namespace App\Modules\Authentication\ApiKey\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent's first-ever API Key was generated — dispatched
 * from RotateApiKeyAction when no prior key existed for the Agent (see
 * that Action's own docblock for why). The API Key submodule has zero
 * knowledge of who, if anyone, is listening.
 */
class ApiKeyGenerated
{
    use Dispatchable;

    public function __construct(
        public readonly string $apiKeyId,
        public readonly string $agentId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
