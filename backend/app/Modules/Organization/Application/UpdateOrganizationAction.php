<?php

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Domain\Events\OrganizationUpdated;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use App\Modules\Organization\Infrastructure\Persistence\OrganizationRepository;

class UpdateOrganizationAction
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
    ) {}

    /**
     * @param  array{name: string}  $data
     */
    public function handle(string $organizationId, array $data, string $actorUserId): Organization
    {
        // organizationId always comes from the AuthenticatedIdentity, so
        // this can never legitimately miss — see 07-authorization.md §7.
        $organization = $this->organizations->findById($organizationId);

        $previousName = $organization->name;

        $organization = $this->organizations->update($organization, $data);

        OrganizationUpdated::dispatch($organizationId, $actorUserId, $previousName, $organization->name);

        return $organization;
    }
}
