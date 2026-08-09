<?php

namespace App\Modules\Agent\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when `name` collides with another Agent in the same Organization —
 * uniqueness is scoped to (organization_id, name), never global. See
 * adr/ADR-003-name-uniqueness-scope.md.
 */
class AgentNameConflictException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONFLICT',
                'message' => 'An Agent with this name already exists in your organization.',
                'details' => [],
            ],
        ], 409);
    }
}
