<?php

namespace App\Modules\Agent\Application;

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAgentsAction
{
    public function __construct(
        private readonly AgentRepository $agents,
    ) {}

    public function handle(string $organizationId, ?AgentStatus $status, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->agents->paginate($organizationId, $status, $perPage, $page);
    }
}
