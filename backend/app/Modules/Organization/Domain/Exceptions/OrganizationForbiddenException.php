<?php

namespace App\Modules\Organization\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated Human lacks the required Role (Owner-only)
 * for PATCH /organization — see 07-authorization.md §3 and
 * adr/ADR-004-audit-and-org-settings-restricted-access.md.
 */
class OrganizationForbiddenException extends RuntimeException
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
