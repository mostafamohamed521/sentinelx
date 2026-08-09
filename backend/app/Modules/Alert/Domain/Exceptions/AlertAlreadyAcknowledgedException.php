<?php

namespace App\Modules\Alert\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Acknowledging an Alert that is already ACKNOWLEDGED or RESOLVED is a 409,
 * never a silent 200 — see 02-domain.md §5 and 06-api-contract.md §3. Same
 * idempotent-as-rejection discipline already established for Agent Archive
 * in Stage 2.
 */
class AlertAlreadyAcknowledgedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONFLICT',
                'message' => 'This Alert has already been acknowledged or resolved.',
                'details' => [],
            ],
        ], 409);
    }
}
