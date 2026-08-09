<?php

namespace App\Modules\Authentication\Identity\API\Middleware;

use App\Modules\Authentication\Identity\Domain\AuthorizationFailedException;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Human role-based access control (06-authorization.md §6-7). Role is
 * resolved from the currently authenticated User, which the "api" guard
 * always retrieves fresh from the database per request — never from the
 * JWT, per ADR-001-role-storage.md. Authorization returns Allow/Deny only;
 * translating Deny into 403 Forbidden is AuthorizationFailedException's
 * job (contracts/auth-errors.md §3), not this middleware's.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var User|null $user */
        $user = $request->user('api');

        $allowed = array_map(fn (string $role) => strtoupper($role), $roles);

        if (! $user || ! in_array($user->role->value, $allowed, true)) {
            throw new AuthorizationFailedException;
        }

        return $next($request);
    }
}
