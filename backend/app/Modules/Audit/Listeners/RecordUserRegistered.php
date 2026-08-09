<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Application\RecordAuditEventAction;
use App\Modules\Audit\Domain\ActorType;
use App\Modules\Authentication\Identity\Domain\Events\UserRegistered;

class RecordUserRegistered
{
    public function __construct(
        private readonly RecordAuditEventAction $recorder,
    ) {}

    public function handle(UserRegistered $event): void
    {
        $this->recorder->handle(
            organizationId: $event->organizationId,
            actorType: ActorType::User,
            actorId: $event->userId,
            action: 'user.registered',
            resourceType: 'User',
            resourceId: $event->userId,
        );
    }
}
