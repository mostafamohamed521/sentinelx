<?php

namespace App\Modules\Alert\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Resolving an Alert that is already RESOLVED is a 409, never a silent 200
 * — see 02-domain.md §6 and 06-api-contract.md §4. RESOLVED is terminal in
 * V1 (ADR-002) — there is no transition out of it, including a second
 * resolve.
 */
class AlertAlreadyResolvedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONFLICT',
                'message' => 'This Alert has already been resolved.',
                'details' => [],
            ],
        ], 409);
    }
}
