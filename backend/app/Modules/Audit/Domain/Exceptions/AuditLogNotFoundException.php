<?php

namespace App\Modules\Audit\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for both a non-existent audit_logs entry and one belonging to a
 * different Organization — indistinguishable by design (Security Through
 * Obscurity), same discipline as every prior module.
 */
class AuditLogNotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Audit log entry not found.',
                'details' => [],
            ],
        ], 404);
    }
}
