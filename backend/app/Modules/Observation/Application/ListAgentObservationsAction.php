<?php

namespace App\Modules\Observation\Application;

use App\Modules\Agent\Application\Contracts\AgentLookupContract;
use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAgentObservationsAction
{
    public function __construct(
        private readonly AgentLookupContract $agents,
        private readonly ObservationRepository $observations,
    ) {}

    /**
     * Validates the Agent exists and belongs to the caller's Organization
     * via Agent's own read-only contract before querying Observations —
     * never a raw, unchecked `WHERE agent_id = ?`. See 07-api-contract.md §4.
     *
     * @throws AgentNotFoundException
     */
    public function handle(string $organizationId, string $agentId, int $perPage, int $page): LengthAwarePaginator
    {
        if (! $this->agents->findActiveAgentForOrganization($agentId, $organizationId)) {
            throw new AgentNotFoundException;
        }

        return $this->observations->listForOrganization($organizationId, $agentId, null, $perPage, $page);
    }
}
