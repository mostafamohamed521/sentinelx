<?php

namespace App\Modules\Organization\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that the Organization was updated — the Organization module
 * has zero knowledge of who, if anyone, is listening. Audit is the one
 * known listener today; see 03-audit-logging.md §2. Structurally identical
 * to AgentArchived (Stage 2) and PredictionStored (Stage 5).
 */
class OrganizationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $actorUserId,
        public readonly string $previousName,
        public readonly string $newName,
    ) {}
}
