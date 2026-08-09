<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Agent\Domain\Events\AgentCreated;
use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;

class RecordAgentCreated
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(AgentCreated $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'agent.created',
            resourceType: 'Agent',
            resourceId: $event->agentId,
            metadata: ['agent_name' => $event->agentName],
        );
    }
}
