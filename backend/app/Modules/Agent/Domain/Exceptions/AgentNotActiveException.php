<?php

namespace App\Modules\Agent\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by the API Key submodule (via AgentLookupContract) when rotating a
 * key for an Agent that is not ACTIVE — see 06-api-contract.md §6.
 */
class AgentNotActiveException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'CONFLICT',
                'message' => 'Cannot issue an API key for an Agent that is not active.',
                'details' => [],
            ],
        ], 409);
    }
}
