<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;

class GetCurrentUserAction
{
    public function handle(): User
    {
        /** @var User $user */
        $user = auth('api')->user();

        return $user;
    }
}
