<?php

namespace App\Modules\Organization\Infrastructure\Persistence;

/**
 * The only place inside the Organization module that touches the
 * `organizations` table directly. No cross-tenant surface exists on these
 * methods — every caller already knows its own organizationId from the
 * AuthenticatedIdentity, and there is no path/query parameter anywhere
 * that could name a different Organization. See 07-authorization.md §7.
 */
class OrganizationRepository
{
    public function findById(string $organizationId): ?Organization
    {
        return Organization::find($organizationId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Organization $organization, array $attributes): Organization
    {
        $organization->update($attributes);

        return $organization->refresh();
    }
}
