<?php

namespace App\Modules\Authentication\ApiKey\Application;

use App\Modules\Agent\Application\Contracts\AgentLookupContract;
use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Domain\Exceptions\AgentNotActiveException;
use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyGenerated;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyRevoked;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyRotated;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;

/**
 * The API Key submodule is allowed to depend on Agent (see
 * 05-module-dependencies.md §4) — this Action is the concrete expression
 * of that allowed direction. It never queries `agents` directly; it only
 * asks the Agent module's own read-only contract.
 *
 * Also the single real, Human-facing entry point for the whole
 * generate/rotate/revoke API Key lifecycle — GenerateApiKeyAction itself
 * is an internal helper with no other caller, so every audit-relevant
 * event dispatch lives here rather than inside it. Whether a call
 * represents an Agent's first-ever key (ApiKeyGenerated) or a genuine
 * rotation replacing an existing one (ApiKeyRotated + ApiKeyRevoked for
 * the key being replaced) is determined from whether a prior key row
 * already existed — see 03-audit-logging.md §3 in the Audit module's docs.
 */
class RotateApiKeyAction
{
    public function __construct(
        private readonly AgentLookupContract $agents,
        private readonly GenerateApiKeyAction $generateApiKey,
    ) {}

    /**
     * @return array{api_key: ApiKey, raw_key: string}
     *
     * @throws AgentNotFoundException
     * @throws AgentNotActiveException
     */
    public function handle(string $organizationId, string $agentId, string $actorUserId): array
    {
        $agent = $this->agents->findActiveAgentForOrganization($agentId, $organizationId);

        if (! $agent) {
            throw new AgentNotFoundException;
        }

        if ($agent->status !== AgentStatus::Active) {
            throw new AgentNotActiveException;
        }

        $priorActiveKey = ApiKey::query()
            ->where('agent_id', $agent->id)
            ->where('status', ApiKeyStatus::Active)
            ->first();

        $result = $this->generateApiKey->handle($agent->id);

        if ($priorActiveKey) {
            ApiKeyRotated::dispatch($result['api_key']->id, $agent->id, $organizationId, $actorUserId);
            ApiKeyRevoked::dispatch($priorActiveKey->id, $agent->id, $organizationId, $actorUserId);
        } else {
            ApiKeyGenerated::dispatch($result['api_key']->id, $agent->id, $organizationId, $actorUserId);
        }

        return $result;
    }
}
