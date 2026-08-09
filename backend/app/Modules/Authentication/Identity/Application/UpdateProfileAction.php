<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Domain\Events\UserProfileUpdated;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;

/**
 * Updates the caller's own record only — there is no user_id parameter
 * anywhere on PATCH /me to even attempt updating anyone else. See
 * 05-profile.md §2 and §4 (role/email are never editable here).
 */
class UpdateProfileAction
{
    /**
     * @param  array{full_name: string}  $data
     */
    public function handle(User $user, array $data): User
    {
        $previousFullName = $user->full_name;

        $user->update(['full_name' => $data['full_name']]);

        UserProfileUpdated::dispatch($user->id, $user->organization_id, $previousFullName, $user->full_name);

        return $user;
    }
}
