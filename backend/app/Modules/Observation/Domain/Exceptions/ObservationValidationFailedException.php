<?php

namespace App\Modules\Observation\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by ObservationValidator on the first failing structural check
 * (Checks 2-5 from 04-validation.md §3 — Check 1 is
 * MalformedObservationPayloadException). Fail-fast, one field/reason pair
 * per failure — matching the exact shape documented in 04-validation.md §5.
 */
class ObservationValidationFailedException extends RuntimeException
{
    public function __construct(
        private readonly string $field,
        private readonly string $reason,
    ) {
        parent::__construct("Observation validation failed: {$field} - {$reason}");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The submitted observation is invalid.',
                'details' => [
                    'field' => $this->field,
                    'reason' => $this->reason,
                ],
            ],
        ], 422);
    }
}
