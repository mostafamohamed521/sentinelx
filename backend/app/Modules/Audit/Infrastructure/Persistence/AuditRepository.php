<?php

namespace App\Modules\Audit\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * The only place inside the Audit module that touches the `audit_logs`
 * table directly. Every read is scoped to organization_id — see
 * 07-authorization.md §7 ("Organization Scoping"). No update()/delete()
 * method exists here, deliberately — audit_logs rows are write-once.
 *
 * GetSecurityLogsAction calls listForOrganization() below with a fixed
 * `actionIn` filter — the exact same query path as the general Audit Log
 * endpoint, per adr/ADR-003-security-logs-is-filtered-audit-view.md. There
 * is no second write path and no second table.
 */
class AuditRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AuditLog
    {
        return AuditLog::create($attributes);
    }

    public function findById(string $auditLogId, string $organizationId): ?AuditLog
    {
        return AuditLog::query()
            ->where('organization_id', $organizationId)
            ->where('id', $auditLogId)
            ->first();
    }

    /**
     * @param  array<int, string>|null  $actionIn
     */
    public function listForOrganization(
        string $organizationId,
        ?string $actorId,
        ?string $action,
        ?string $resourceType,
        ?Carbon $from,
        ?Carbon $to,
        ?array $actionIn,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return AuditLog::query()
            ->where('organization_id', $organizationId)
            ->when($actorId, fn ($query) => $query->where('actor_id', $actorId))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($resourceType, fn ($query) => $query->where('resource_type', $resourceType))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            // `when($actionIn, ...)` would treat an empty array as falsy and
            // skip the filter entirely — wrong here, since
            // GetSecurityLogsAction deliberately passes [] to mean "match
            // nothing" (an `action` filter outside the fixed security set).
            // Explicit null-check instead.
            ->when($actionIn !== null, fn ($query) => $query->whereIn('action', $actionIn))
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }
}
