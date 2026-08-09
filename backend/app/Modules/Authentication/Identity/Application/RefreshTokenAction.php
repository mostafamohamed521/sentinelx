<?php

namespace App\Modules\Authentication\Identity\Application;

use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RefreshTokenAction
{
    public function handle(): string
    {
        return JWTAuth::parseToken()->refresh();
    }
}
