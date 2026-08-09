<?php

namespace App\Modules\Authentication\Identity\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A self-action — the actor is the User logging in.
 */
class UserLoggedIn
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
    ) {}
}
