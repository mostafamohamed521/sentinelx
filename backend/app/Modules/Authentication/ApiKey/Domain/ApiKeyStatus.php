<?php

namespace App\Modules\Authentication\ApiKey\Domain;

enum ApiKeyStatus: string
{
    case Active = 'ACTIVE';
    case Revoked = 'REVOKED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
