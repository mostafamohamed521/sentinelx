<?php

namespace App\Modules\Analysis\Domain;

enum Verdict: string
{
    case Safe = 'SAFE';
    case Suspicious = 'SUSPICIOUS';
    case Malicious = 'MALICIOUS';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
