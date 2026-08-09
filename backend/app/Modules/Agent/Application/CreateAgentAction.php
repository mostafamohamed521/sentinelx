<?php

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\AgentPolicy;
use App\Modules\Agent\Domain\Events\AgentCreated;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;

class CreateAgentAction
{
    public function __construct(
        private readonly AgentRepository $agents,
        private readonly AgentPolicy $policy,
    ) {}

    /**
     * @param  array{name: string, framework: string, framework_version?: string|null, description?: string|null}  $data
     */
    public function handle(string $organizationId, array $data, string $actorUserId): Agent
    {
        $this->policy->ensureNameIsAvailable(
            $this->agents->nameExists($organizationId, $data['name'])
        );

        $agent = $this->agents->create($organizationId, $data);

        AgentCreated::dispatch($agent->id, $organizationId, $actorUserId, $agent->name);

        return $agent;
    }
}
