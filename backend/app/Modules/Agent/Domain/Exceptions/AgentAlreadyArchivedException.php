<?php

namespace App\Modules\Agent\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Archiving an already-ARCHIVED Agent is a 409, never a silent 200 — see
 * 02-domain.md §4. Silently succeeding would hide a caller's stale
 * assumption about the Agent's state from them.
 */
class AgentAlreadyArchivedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONFLICT',
                'message' => 'This Agent is already archived.',
                'details' => [],
            ],
        ], 409);
    }
}
