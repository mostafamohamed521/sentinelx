<?php

namespace App\Modules\Authentication\Identity\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A self-action — the actor is the User being registered. Audit is the one
 * known listener today; see 03-audit-logging.md §2.
 */
class UserRegistered
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
    ) {}
}
