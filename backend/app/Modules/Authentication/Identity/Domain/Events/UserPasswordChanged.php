<?php

namespace App\Modules\Authentication\Identity\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A self-action — the actor is the User changing their own password.
 * Deliberately carries no password/hash data — see 05-profile.md §7.
 */
class UserPasswordChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
    ) {}
}
