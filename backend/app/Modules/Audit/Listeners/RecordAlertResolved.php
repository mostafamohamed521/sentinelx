<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Alert\Domain\Events\AlertResolved;
use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;

class RecordAlertResolved
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(AlertResolved $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'alert.resolved',
            resourceType: 'Alert',
            resourceId: $event->alertId,
        );
    }
}
