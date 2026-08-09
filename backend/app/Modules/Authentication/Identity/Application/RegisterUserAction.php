<?php

namespace App\Modules\Authentication\Identity\Application;

use App\Modules\Authentication\Identity\Domain\Events\UserRegistered;
use App\Modules\Authentication\Identity\Domain\UserRole;
use App\Modules\Authentication\Identity\Domain\UserStatus;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Application\CreateOrganizationAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function __construct(
        // Direct use of Organization's own Action — Authentication is
        // allowed to depend on Organization (05-module-dependencies.md's
        // "Complete Picture" diagram). See 04-organization-settings.md §4.
        private readonly CreateOrganizationAction $createOrganization,
    ) {}

    /**
     * Register creates a new Organization and its first User, who
     * is always the Owner — never a bare User. See
     * 08-identity-lifecycle.md §2-3.
     *
     * @param  array{organization_name: string, full_name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $organization = $this->createOrganization->handle(['name' => $data['organization_name']]);

            /** @var User $user */
            $user = $organization->users()->create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'password_hash' => Hash::make($data['password']),
                'role' => UserRole::Owner,
                'status' => UserStatus::Active,
            ]);

            $user->sendEmailVerificationNotification();

            return $user;
        });

        UserRegistered::dispatch($user->id, $user->organization_id);

        return $user;
    }
}
