<?php

namespace App\Modules\Authentication\Identity\Domain;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated identity is denied by Authorization (role or
 * capability check) — distinct from AuthenticationFailedException, since the
 * identity itself was already verified. See contracts/auth-errors.md §3.
 *
 * Renders the platform-wide nested error envelope
 * (docs/09-api-reference/07-ERROR_CODES.md) — migrated from a legacy flat
 * shape the Frontend's parser never actually understood (CONTRACT-009).
 */
class AuthorizationFailedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to perform this action.',
                'details' => [],
            ],
        ], 403);
    }
}
