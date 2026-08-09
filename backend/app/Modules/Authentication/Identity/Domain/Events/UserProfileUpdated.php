<?php

namespace App\Modules\Authentication\Identity\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A self-action — the actor is the User updating their own profile.
 */
class UserProfileUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $userId,
        public readonly string $organizationId,
        public readonly string $previousFullName,
        public readonly string $newFullName,
    ) {}
}
