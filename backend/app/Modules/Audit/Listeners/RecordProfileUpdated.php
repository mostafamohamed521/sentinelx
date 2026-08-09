<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;
use App\Modules\Authentication\Identity\Domain\Events\UserProfileUpdated;

class RecordProfileUpdated
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(UserProfileUpdated $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->userId,
            action: 'user.profile_updated',
            resourceType: 'User',
            resourceId: $event->userId,
            metadata: ['previous_full_name' => $event->previousFullName, 'new_full_name' => $event->newFullName],
        );
    }
}
