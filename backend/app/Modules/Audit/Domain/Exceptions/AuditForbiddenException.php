<?php

namespace App\Modules\Audit\Domain\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an authenticated Human's Role is Member, attempting any
 * Audit/Security Log endpoint — Owner and Admin only, Member deliberately
 * excluded. See 07-authorization.md §4 and
 * adr/ADR-004-audit-and-org-settings-restricted-access.md. This is the
 * first Member-gets-403-on-a-GET decision in this entire series.
 */
class AuditForbiddenException extends RuntimeException
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
