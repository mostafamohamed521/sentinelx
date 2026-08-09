<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyRevoked;

class RecordApiKeyRevoked
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(ApiKeyRevoked $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'api_key.revoked',
            resourceType: 'ApiKey',
            resourceId: $event->apiKeyId,
            metadata: ['agent_id' => $event->agentId],
        );
    }
}
