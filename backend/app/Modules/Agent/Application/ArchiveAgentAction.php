<?php

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\AgentPolicy;
use App\Modules\Agent\Domain\Events\AgentArchived;
use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;

class ArchiveAgentAction
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly AgentPolicy $policy,
    ) {}

    /**
     * @throws AgentNotFoundException
     */
    public function handle(string $organizationId, string $agentId): Agent
    {
        $agent = $this->agents->findById($agentId, $organizationId);

        if (! $agent) {
            throw new AgentNotFoundException;
        }

        $this->policy->ensureCanBeArchived($agent->status);

        $agent = $this->agents->archive($agent);

        AgentArchived::dispatch($agent->id, $organizationId);

        return $agent;
    }
}
