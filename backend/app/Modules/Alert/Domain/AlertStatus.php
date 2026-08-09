<?php

namespace App\Modules\Alert\Domain;

enum AlertStatus: string
{
    case Open = 'OPEN';
    case Acknowledged = 'ACKNOWLEDGED';
    case Resolved = 'RESOLVED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
