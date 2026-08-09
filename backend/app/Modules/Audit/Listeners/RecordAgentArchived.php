<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Agent\Domain\Events\AgentArchived;
use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;

/**
 * A SECOND listener on the Stage-2 AgentArchived event — Stage 2's own
 * code (the event itself, ArchiveAgentAction, and Authentication's
 * RevokeKeysOnAgentArchived listener) is completely untouched. See
 * 03-audit-logging.md §2.
 *
 * AgentArchived carries only (agentId, organizationId) — it was designed
 * before this Sprint and is deliberately not modified to add an actor
 * field (the instruction is explicit: "don't touch the event itself").
 * Since this listener runs synchronously, in the same request, immediately
 * after ArchiveAgentAction's own already-authenticated HTTP request, the
 * acting User is resolved from the current auth('api') guard state rather
 * than from the event payload — the one listener in this module that does
 * this, and the only one that needs to.
 */
class RecordAgentArchived
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(AgentArchived $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: (string) auth('api')->id(),
            action: 'agent.archived',
            resourceType: 'Agent',
            resourceId: $event->agentId,
        );
    }
}
