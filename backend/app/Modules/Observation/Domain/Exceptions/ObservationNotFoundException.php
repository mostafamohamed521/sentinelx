<?php

namespace App\Modules\Observation\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for both a non-existent Observation ID and an Observation
 * belonging to a different Organization — indistinguishable by design
 * (Security Through Obscurity), see 06-authorization.md §4.
 */
class ObservationNotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Observation not found.',
                'details' => [],
            ],
        ], 404);
    }
}
