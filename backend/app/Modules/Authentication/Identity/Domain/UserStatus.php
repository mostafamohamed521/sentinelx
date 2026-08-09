<?php

namespace App\Modules\Authentication\Identity\Domain;

enum UserStatus: string
{
    case Active = 'ACTIVE';
    case Disabled = 'DISABLED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
