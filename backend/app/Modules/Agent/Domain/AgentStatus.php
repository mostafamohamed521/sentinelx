<?php

namespace App\Modules\Agent\Domain;

enum AgentStatus: string
{
    case Active = 'ACTIVE';
    case Archived = 'ARCHIVED';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
