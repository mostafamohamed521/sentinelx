<?php

namespace App\Modules\Agent\Application\Contracts;

use App\Modules\Agent\Infrastructure\Persistence\Agent;

/**
 * The entire legal surface between Authentication's API Key submodule and
 * the Agent module — read-only, no write methods. See
 * 04-api-key-coordination.md §2. Defined here (Application layer) and
 * implemented in Infrastructure, so the dependency points the right way:
 * Authentication depends on this interface, never on Agent's persistence
 * details directly.
 */
interface AgentLookupContract
{
    /**
     * Resolves an Agent scoped to the given Organization, regardless of its
     * current status. Callers (e.g. RotateApiKeyAction) are responsible for
     * checking `status` themselves and choosing 404 (not found/cross-tenant)
     * vs 409 (found but ARCHIVED) — see 06-api-contract.md §6. Returning
     * only ACTIVE Agents here would collapse both cases into an
     * indistinguishable null, which the documented error table forbids.
     */
    public function findActiveAgentForOrganization(string $agentId, string $organizationId): ?Agent;
}
