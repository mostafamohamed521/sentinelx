<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Agent\Domain\Events\AgentUpdated;
use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;

class RecordAgentUpdated
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(AgentUpdated $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'agent.updated',
            resourceType: 'Agent',
            resourceId: $event->agentId,
        );
    }
}
