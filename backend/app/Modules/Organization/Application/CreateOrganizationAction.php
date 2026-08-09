<?php

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Domain\OrganizationStatus;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Str;

/**
 * Extracted from Authentication\Identity\RegisterUserAction, per
 * 04-organization-settings.md §4 — Stage 1's registration flow created the
 * `organizations` row inline; this Sprint re-homes that logic here without
 * changing RegisterUserAction's external behavior. Organization owns
 * Create Organization per module-responsibilities.md §3.
 */
class CreateOrganizationAction
{
    /**
     * @param  array{name: string}  $data
     */
    public function handle(array $data): Organization
    {
        return Organization::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'status' => OrganizationStatus::Active,
        ]);
    }

    private function uniqueSlug(string $organizationName): string
    {
        $base = Str::slug($organizationName);
        $slug = $base;
        $suffix = 1;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
