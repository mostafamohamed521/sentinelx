<?php

namespace App\Modules\Agent\Domain;

use App\Modules\Agent\Domain\Exceptions\AgentAlreadyArchivedException;
use App\Modules\Agent\Domain\Exceptions\AgentNameConflictException;

/**
 * Pure business-rule checks for the Agent entity — no Eloquent, no HTTP.
 * See 02-domain.md §4 (archive idempotency) and adr/ADR-003 (name scope).
 */
class AgentPolicy
{
    /**
     * @throws AgentAlreadyArchivedException
     */
    public function ensureCanBeArchived(AgentStatus $currentStatus): void
    {
        if ($currentStatus === AgentStatus::Archived) {
            throw new AgentAlreadyArchivedException;
        }
    }

    /**
     * @throws AgentNameConflictException
     */
    public function ensureNameIsAvailable(bool $nameAlreadyTaken): void
    {
        if ($nameAlreadyTaken) {
            throw new AgentNameConflictException;
        }
    }
}
