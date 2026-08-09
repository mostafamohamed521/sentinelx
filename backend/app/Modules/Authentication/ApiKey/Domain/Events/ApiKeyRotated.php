<?php

namespace App\Modules\Authentication\ApiKey\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent's API Key was rotated (a new key replaced an
 * existing one) — dispatched from RotateApiKeyAction on every call where
 * a prior key already existed.
 */
class ApiKeyRotated
{
    use Dispatchable;

    public function __construct(
        public readonly string $apiKeyId,
        public readonly string $agentId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
