<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;
use App\Modules\Organization\Domain\Events\OrganizationUpdated;

class RecordOrganizationUpdated
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(OrganizationUpdated $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->actorUserId,
            action: 'organization.updated',
            resourceType: 'Organization',
            resourceId: $event->organizationId,
            metadata: ['previous_name' => $event->previousName, 'new_name' => $event->newName],
        );
    }
}
