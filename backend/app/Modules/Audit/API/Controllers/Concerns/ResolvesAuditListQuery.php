<?php

namespace App\Modules\Audit\API\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared between AuditController and SecurityLogController — both accept
 * the identical query parameter set (08-api-contract.md §7: "Same query
 * parameters and response shape as §5"). Kept as a small, module-internal
 * trait rather than duplicating these two helpers across both controllers.
 */
trait ResolvesAuditListQuery
{
    private function organizationId(Request $request): string
    {
        return (string) $request->user('api')->organization_id;
    }

    private function dateQuery(Request $request, string $key): ?Carbon
    {
        $value = $request->query($key);

        return $value ? Carbon::parse($value) : null;
    }
}
