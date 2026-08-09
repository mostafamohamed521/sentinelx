<?php

namespace App\Modules\Authentication\ApiKey\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Agent's prior API Key was revoked as a direct
 * consequence of a Human-initiated rotate call — dispatched from
 * RotateApiKeyAction alongside ApiKeyRotated, when a prior ACTIVE key
 * existed and was auto-revoked by ApiKey::booted(). Deliberately NOT
 * dispatched from the Agent-archive cascade (RevokeKeysOnAgentArchived) —
 * that revocation is a system-triggered side effect of AgentArchived,
 * already fully captured by the agent.archived audit entry itself; a
 * second entry for the same underlying Human action would be
 * accountability noise, not new information. See
 * adr/ADR-002-audit-scoped-to-human-initiated-actions.md in the Audit
 * module's own docs for the same reasoning applied to Alert creation.
 */
class ApiKeyRevoked
{
    use Dispatchable;

    public function __construct(
        public readonly string $apiKeyId,
        public readonly string $agentId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
