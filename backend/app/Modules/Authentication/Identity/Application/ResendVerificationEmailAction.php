<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;

class ResendVerificationEmailAction
{
    public function handle(User $user): string
    {
        if ($user->hasVerifiedEmail()) {
            return 'Email already verified.';
        }

        $user->sendEmailVerificationNotification();

        return 'Verification link sent.';
    }
}
