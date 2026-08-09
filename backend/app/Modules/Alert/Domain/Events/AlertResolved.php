<?php

namespace App\Modules\Alert\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Alert was resolved — the Alert module has zero
 * knowledge of who, if anyone, is listening.
 */
class AlertResolved
{
    use Dispatchable;

    public function __construct(
        public readonly string $alertId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
