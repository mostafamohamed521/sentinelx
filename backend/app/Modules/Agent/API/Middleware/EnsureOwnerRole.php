<?php

namespace App\Modules\Agent\API\Middleware;

use App\Modules\Agent\Domain\Exceptions\AgentForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner-only gate for Agent-management and API-Key-rotation endpoints —
 * see 05-authorization.md §2. Lives in the Agent module (Authentication is
 * allowed to depend on Agent, so its rotate-api-key route reuses this
 * middleware too — see 04-api-key-coordination.md §1). Kept separate from
 * Identity\API\Middleware\EnsureUserHasRole because this module's error
 * envelope is the nested `{error:{code,message,details}}` shape mandated
 * by 06-api-contract.md, not Identity's flat, already-frozen shape.
 *
 * Deliberately does not import the User model or UserRole enum from
 * Authentication\Identity — the Agent module must never depend on
 * Authentication's Infrastructure/Application layers (see
 * 04-api-key-coordination.md §3). The authenticated Human's role is read
 * dynamically off whatever object the "api" guard resolves, exactly the
 * same runtime contract every other guard-consuming middleware relies on.
 */
class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if (! $user || ($user->role?->value ?? null) !== 'OWNER') {
            throw new AgentForbiddenException;
        }

        return $next($request);
    }
}
