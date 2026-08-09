<?php

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\AgentPolicy;
use App\Modules\Agent\Domain\Events\AgentUpdated;
use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;

class UpdateAgentAction
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly AgentPolicy $policy,
    ) {}

    /**
     * @param  array{name?: string, framework?: string, framework_version?: string|null, description?: string|null}  $data
     *
     * @throws AgentNotFoundException
     */
    public function handle(string $organizationId, string $agentId, array $data, string $actorUserId): Agent
    {
        $agent = $this->agents->findById($agentId, $organizationId);

        if (! $agent) {
            throw new AgentNotFoundException;
        }

        if (isset($data['name'])) {
            $this->policy->ensureNameIsAvailable(
                $this->agents->nameExists($organizationId, $data['name'], excludeAgentId: $agent->id)
            );
        }

        $agent = $this->agents->update($agent, $data);

        AgentUpdated::dispatch($agent->id, $organizationId, $actorUserId);

        return $agent;
    }
}
