<?php

namespace App\Modules\Alert\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for both a non-existent Alert ID and an Alert belonging to a
 * different Organization — indistinguishable by design (Security Through
 * Obscurity), see 04-authorization.md §5.
 */
class AlertNotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Alert not found.',
                'details' => [],
            ],
        ], 404);
    }
}
