<?php

namespace App\Modules\Audit\Application;

use App\Modules\Audit\Infrastructure\Persistence\AuditRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Thin wrapper — calls the SAME AuditRepository::listForOrganization() used
 * by the general Audit Log endpoint, with a fixed action-list filter
 * applied. No second table, no second write path — see
 * 06-security-logs.md §3 and adr/ADR-003-security-logs-is-filtered-audit-view.md.
 */
class GetSecurityLogsAction
{
    /**
     * The "security" category, per 06-security-logs.md §2 — an engineering
     * default, explicitly flagged as easily revised, same discipline as
     * Alert's severity thresholds (ADR-001 in that module).
     *
     * @var array<int, string>
     */
    private const SECURITY_ACTIONS = [
        'user.registered',
        'user.logged_in',
        'user.logged_out',
        'user.password_changed',
        'api_key.generated',
        'api_key.rotated',
        'api_key.revoked',
    ];

    public function __construct(
        private readonly AuditRepository $audits,
    ) {}

    public function handle(
        string $organizationId,
        ?string $actorId,
        ?string $action,
        ?string $resourceType,
        ?Carbon $from,
        ?Carbon $to,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        // If `action` is also supplied, it narrows WITHIN the fixed
        // security set — it never expands beyond it. An `action` outside
        // the set correctly yields zero rows (whereIn on an empty array),
        // rather than silently falling back to the full set. See
        // 08-api-contract.md §7.
        $actionIn = self::SECURITY_ACTIONS;

        if ($action) {
            $actionIn = in_array($action, self::SECURITY_ACTIONS, true) ? [$action] : [];
        }

        return $this->audits->listForOrganization(
            organizationId: $organizationId,
            actorId: $actorId,
            action: null,
            resourceType: $resourceType,
            from: $from,
            to: $to,
            actionIn: $actionIn,
            perPage: $perPage,
            page: $page,
        );
    }
}
