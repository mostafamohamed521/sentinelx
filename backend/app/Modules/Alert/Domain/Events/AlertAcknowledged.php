<?php

namespace App\Modules\Alert\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Announces that an Alert was acknowledged — the Alert module has zero
 * knowledge of who, if anyone, is listening. Audit is the one known
 * listener today.
 */
class AlertAcknowledged
{
    use Dispatchable;

    public function __construct(
        public readonly string $alertId,
        public readonly string $organizationId,
        public readonly string $actorUserId,
    ) {}
}
