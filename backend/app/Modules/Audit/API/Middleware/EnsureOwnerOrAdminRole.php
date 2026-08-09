<?php

namespace App\Modules\Audit\API\Middleware;

use App\Modules\Audit\Domain\Exceptions\AuditForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner+Admin gate for every Audit/Security Log endpoint — Member
 * excluded. See 07-authorization.md §4. A dedicated copy rather than
 * reusing another module's middleware: Audit depends on nothing and
 * nothing depends on Audit (05-module-dependencies.md §16), so it must
 * never import another module's Infrastructure/Domain layer.
 */
class EnsureOwnerOrAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $role = $user->role?->value ?? null;

        if (! $user || ! in_array($role, ['OWNER', 'ADMIN'], true)) {
            throw new AuditForbiddenException;
        }

        return $next($request);
    }
}
