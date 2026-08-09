<?php

namespace App\Modules\Audit\Domain;

/**
 * Only two values — SYSTEM exists for a rare platform-initiated action
 * with no Human actor (none currently exists in V1). AGENT is deliberately
 * absent: Agent actions (Observation submission) are never audited, per
 * adr/ADR-002-audit-scoped-to-human-initiated-actions.md — they're already
 * fully, immutably recorded in `observations` itself.
 */
enum ActorType: string
{
    case User = 'USER';
    case System = 'SYSTEM';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
