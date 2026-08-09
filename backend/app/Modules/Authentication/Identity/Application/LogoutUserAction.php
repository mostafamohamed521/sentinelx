<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Domain\Events\UserLoggedOut;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;

class LogoutUserAction
{
    /**
     * Logout is client-side only in V1 — no server-side token blacklist
     * exists yet, matching 04-jwt.md §9. This Action exists so the
     * controller has a single, testable point of extension if that changes.
     *
     * Resolves the current User the same way GetCurrentUserAction does
     * (auth('api')->user()) — this route sits inside the auth:api group,
     * so the identity is always available here.
     */
    public function handle(): void
    {
        /** @var User $user */
        $user = auth('api')->user();

        UserLoggedOut::dispatch($user->id, $user->organization_id);
    }
}
