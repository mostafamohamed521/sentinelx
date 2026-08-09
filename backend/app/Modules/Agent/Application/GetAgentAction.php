<?php

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;

class GetAgentAction
{
    public function __construct(
        private readonly AgentRepository $agents,
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

        return $agent;
    }
}
