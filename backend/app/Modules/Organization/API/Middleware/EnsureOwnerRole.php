<?php

namespace App\Modules\Organization\API\Middleware;

use App\Modules\Organization\Domain\Exceptions\OrganizationForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner-only gate for PATCH /organization — see 07-authorization.md §3.
 * A dedicated copy rather than reusing Agent's EnsureOwnerRole: Organization
 * sits beneath Agent in the dependency chain (Agent -> Organization), so
 * Organization must never import anything from Agent — see
 * 05-module-dependencies.md §12, "Lower Modules Never Know Higher Modules."
 */
class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (! $user || ($user->role?->value ?? null) !== 'OWNER') {
            throw new OrganizationForbiddenException;
        }

        return $next($request);
    }
}
