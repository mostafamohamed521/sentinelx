<?php

namespace App\Modules\Agent\Infrastructure\Persistence;

use App\Modules\Agent\Application\Contracts\AgentLookupContract;
use App\Modules\Agent\Application\Contracts\AgentSummaryContract;
use App\Modules\Agent\Domain\AgentStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The only place inside the Agent module that touches the `agents` table
 * directly. Every method is scoped to an organization_id — see
 * 05-authorization.md §4 ("Scoped Queries", 404 not 403 for cross-tenant).
 */
class AgentRepository implements AgentLookupContract, AgentSummaryContract
{
    public function create(string $organizationId, array $attributes): Agent
    {
        return Agent::create([...$attributes, 'organization_id' => $organizationId]);
    }

    public function findById(string $agentId, string $organizationId): ?Agent
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
            ->where('id', $agentId)
            ->first();
    }

    public function nameExists(string $organizationId, string $name, ?string $excludeAgentId = null): bool
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
            ->where('name', $name)
            ->when($excludeAgentId, fn ($query) => $query->where('id', '!=', $excludeAgentId))
            ->exists();
    }

    public function paginate(string $organizationId, ?AgentStatus $status, int $perPage, int $page): LengthAwarePaginator
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function update(Agent $agent, array $attributes): Agent
    {
        $agent->update($attributes);

        return $agent->refresh();
    }

    public function archive(Agent $agent): Agent
    {
        $agent->update(['status' => AgentStatus::Archived]);

        return $agent->refresh();
    }

    /**
     * The one method any other module (currently: Observation, in a later
     * Stage) is allowed to call to write to `agents` — see 02-domain.md §3.
     */
    public function touchLastSeen(string $agentId, Carbon $timestamp): void
    {
        Agent::query()->where('id', $agentId)->update(['last_seen_at' => $timestamp]);
    }

    public function findActiveAgentForOrganization(string $agentId, string $organizationId): ?Agent
    {
        return $this->findById($agentId, $organizationId);
    }

    public function countTotalForOrganization(string $organizationId): int
    {
        return Agent::query()->where('organization_id', $organizationId)->count();
    }

    public function countActiveForOrganization(string $organizationId): int
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
            ->where('status', AgentStatus::Active)
            ->count();
    }

    /**
     * @return array<int, Agent>
     */
    public function listRecentlyActiveForOrganization(string $organizationId, int $limit): array
    {
        return Agent::query()
            ->where('organization_id', $organizationId)
            ->where('status', AgentStatus::Active)
            ->whereNotNull('last_seen_at')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get()
            ->all();
    }
}
