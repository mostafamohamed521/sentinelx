<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyGenerated;

class RecordApiKeyGenerated
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(ApiKeyGenerated $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'api_key.generated',
            resourceType: 'ApiKey',
            resourceId: $event->apiKeyId,
            metadata: ['agent_id' => $event->agentId],
        );
    }
}
