<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Alert\Domain\Events\AlertAcknowledged;
use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;

class RecordAlertAcknowledged
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(AlertAcknowledged $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'alert.acknowledged',
            resourceType: 'Alert',
            resourceId: $event->alertId,
        );
    }
}
