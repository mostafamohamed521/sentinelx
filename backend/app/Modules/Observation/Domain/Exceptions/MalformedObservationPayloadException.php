<?php

namespace App\Modules\Observation\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Check 1 from 04-validation.md §3 — the request body doesn't parse as
 * JSON at all. A transport-level problem, distinct from (and checked
 * before) ObservationValidationFailedException's structural checks.
 */
class MalformedObservationPayloadException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'BAD_REQUEST',
                'message' => 'The request body is not valid JSON.',
                'details' => [],
            ],
        ], 400);
    }
}
