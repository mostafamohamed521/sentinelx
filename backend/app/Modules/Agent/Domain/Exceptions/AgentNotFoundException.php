<?php

namespace App\Modules\Agent\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown for both a non-existent Agent ID and an Agent belonging to a
 * different Organization — the two cases are indistinguishable by design
 * (Security Through Obscurity), see 05-authorization.md §4.
 */
class AgentNotFoundException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'Agent not found.',
                'details' => [],
            ],
        ], 404);
    }
}
