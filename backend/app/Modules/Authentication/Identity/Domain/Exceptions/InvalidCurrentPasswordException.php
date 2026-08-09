<?php

namespace App\Modules\Authentication\Identity\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by POST /me/change-password when current_password doesn't match
 * — see 05-profile.md §5 and 08-api-contract.md §4. Uses this module's
 * nested error shape (matching every module since Agent, Stage 2), not
 * AuthenticationFailedException's older, flat shape — this isn't a login
 * failure, it's a re-confirmation failure on an already-authenticated
 * request.
 */
class InvalidCurrentPasswordException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'The provided current password is incorrect.',
                'details' => [],
            ],
        ], 401);
    }
}
