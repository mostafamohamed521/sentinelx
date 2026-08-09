<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Domain\Events\UserPasswordChanged;
use App\Modules\Authentication\Identity\Domain\Exceptions\InvalidCurrentPasswordException;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    /**
     * @param  array{current_password: string, new_password: string}  $data
     *
     * @throws InvalidCurrentPasswordException
     */
    public function handle(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password_hash)) {
            throw new InvalidCurrentPasswordException;
        }

        $user->forceFill(['password_hash' => Hash::make($data['new_password'])])->save();

        UserPasswordChanged::dispatch($user->id, $user->organization_id);
    }
}
