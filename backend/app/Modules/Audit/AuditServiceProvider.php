<?php

namespace App\Modules\Audit;

use App\Modules\Agent\Domain\Events\AgentArchived;
use App\Modules\Agent\Domain\Events\AgentCreated;
use App\Modules\Agent\Domain\Events\AgentUpdated;
use App\Modules\Alert\Domain\Events\AlertAcknowledged;
use App\Modules\Alert\Domain\Events\AlertResolved;
use App\Modules\Audit\Listeners\RecordAgentArchived;
use App\Modules\Audit\Listeners\RecordAgentCreated;
use App\Modules\Audit\Listeners\RecordAgentUpdated;
use App\Modules\Audit\Listeners\RecordAlertAcknowledged;
use App\Modules\Audit\Listeners\RecordAlertResolved;
use App\Modules\Audit\Listeners\RecordApiKeyGenerated;
use App\Modules\Audit\Listeners\RecordApiKeyRevoked;
use App\Modules\Audit\Listeners\RecordApiKeyRotated;
use App\Modules\Audit\Listeners\RecordOrganizationUpdated;
use App\Modules\Audit\Listeners\RecordPasswordChanged;
use App\Modules\Audit\Listeners\RecordProfileUpdated;
use App\Modules\Audit\Listeners\RecordUserLoggedIn;
use App\Modules\Audit\Listeners\RecordUserLoggedOut;
use App\Modules\Audit\Listeners\RecordUserRegistered;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyGenerated;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyRevoked;
use App\Modules\Authentication\ApiKey\Domain\Events\ApiKeyRotated;
use App\Modules\Authentication\Identity\Domain\Events\UserLoggedIn;
use App\Modules\Authentication\Identity\Domain\Events\UserLoggedOut;
use App\Modules\Authentication\Identity\Domain\Events\UserPasswordChanged;
use App\Modules\Authentication\Identity\Domain\Events\UserProfileUpdated;
use App\Modules\Authentication\Identity\Domain\Events\UserRegistered;
use App\Modules\Organization\Domain\Events\OrganizationUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Audit as a listener against every audited event, per
 * 03-audit-logging.md §3. Every emitting module (Organization,
 * Authentication, Agent, Alert) has zero reference to this provider or to
 * anything inside the Audit module — the same discipline already
 * established for AgentArchived (Stage 2) and PredictionStored (Stage 5).
 */
class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrganizationUpdated::class, RecordOrganizationUpdated::class);

        Event::listen(UserRegistered::class, RecordUserRegistered::class);
        Event::listen(UserLoggedIn::class, RecordUserLoggedIn::class);
        Event::listen(UserLoggedOut::class, RecordUserLoggedOut::class);
        Event::listen(UserProfileUpdated::class, RecordProfileUpdated::class);
        Event::listen(UserPasswordChanged::class, RecordPasswordChanged::class);

        Event::listen(AgentCreated::class, RecordAgentCreated::class);
        Event::listen(AgentUpdated::class, RecordAgentUpdated::class);
        // A SECOND listener on the Stage-2 AgentArchived event — that
        // event and its original listener (RevokeKeysOnAgentArchived) are
        // untouched.
        Event::listen(AgentArchived::class, RecordAgentArchived::class);

        Event::listen(ApiKeyGenerated::class, RecordApiKeyGenerated::class);
        Event::listen(ApiKeyRotated::class, RecordApiKeyRotated::class);
        Event::listen(ApiKeyRevoked::class, RecordApiKeyRevoked::class);

        Event::listen(AlertAcknowledged::class, RecordAlertAcknowledged::class);
        Event::listen(AlertResolved::class, RecordAlertResolved::class);
    }
}
