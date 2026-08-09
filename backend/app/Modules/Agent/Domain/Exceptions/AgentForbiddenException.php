<?php

namespace App\Modules\Agent\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated Human lacks the required Role (Owner-only
 * endpoints) for an Agent-management or API-Key-rotation action — see
 * 05-authorization.md §2.
 */
class AgentForbiddenException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'You do not have permission to perform this action.',
                'details' => [],
            ],
        ], 403);
    }
}
