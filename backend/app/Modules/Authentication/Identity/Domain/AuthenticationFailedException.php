<?php

namespace App\Modules\Authentication\Identity\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for every authentication failure, regardless of underlying cause
 * (wrong password, unknown email, disabled user, unverified email, revoked
 * API key, archived agent, expired/invalid JWT...). The client always
 * receives the same generic response — see contracts/auth-errors.md §1-2.
 * The specific reason is for logs only, never the response body.
 *
 * Renders the platform-wide nested error envelope
 * (docs/09-api-reference/07-ERROR_CODES.md) — migrated from a legacy flat
 * shape the Frontend's parser never actually understood (CONTRACT-009).
 * This is also the ONE definition of "what does an authentication failure
 * look like" — bootstrap/app.php's render for the framework's own
 * Illuminate\Auth\AuthenticationException (a different trigger: a missing/
 * invalid token at the guard layer, vs. this class's explicit throw from
 * LoginUserAction) reuses this method directly rather than hand-authoring a
 * second copy of the shape (ERROR-004).
 */
class AuthenticationFailedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'AUTHENTICATION_FAILED',
                'message' => 'Authentication failed.',
                'details' => [],
            ],
        ], 401);
    }
}
