<?php

namespace App\Modules\Alert\Infrastructure\Persistence;

use App\Modules\Alert\Application\Contracts\AlertSummaryContract;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Domain\Severity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The only place inside the Alert module that touches the `alerts` table
 * directly. `alerts` has no organization_id column of its own (unlike
 * `observations`' deliberate denormalization) — every organization-scoped
 * query here joins through Prediction -> Observation to reach
 * observations.organization_id. This is the one, explicitly-sanctioned
 * exception to "never query predictions/observations directly" (see
 * 04-authorization.md §5): it's a structural join for scoping only — the
 * actual Prediction/Observation business data returned to callers still
 * always comes from their own modules' LookupContracts (see
 * GetAlertAction), never from this join.
 */
class AlertRepository implements AlertSummaryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Alert
    {
        return Alert::create($attributes);
    }

    public function findById(string $alertId, string $organizationId): ?Alert
    {
        return $this->scopedToOrganization($organizationId)
            ->where('id', $alertId)
            ->first();
    }

    public function findByPredictionId(string $predictionId): ?Alert
    {
        return Alert::query()
            ->where('prediction_id', $predictionId)
            ->first();
    }

    public function listForOrganization(
        string $organizationId,
        ?AlertStatus $status,
        ?Severity $severity,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        // Eager-loaded: AlertSummaryResource now reads reasons off the
        // related Prediction (RC-3) — without this, that would be an N+1
        // query, once per Alert in the page.
        return $this->scopedToOrganization($organizationId)
            ->with('prediction')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($severity, fn ($query) => $query->where('severity', $severity))
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function acknowledge(string $alertId, string $userId, Carbon $acknowledgedAt): Alert
    {
        Alert::query()->where('id', $alertId)->update([
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => $acknowledgedAt,
            'acknowledged_by' => $userId,
        ]);

        return Alert::findOrFail($alertId);
    }

    public function resolve(string $alertId, string $userId, Carbon $resolvedAt): Alert
    {
        Alert::query()->where('id', $alertId)->update([
            'status' => AlertStatus::Resolved,
            'resolved_at' => $resolvedAt,
            'resolved_by' => $userId,
        ]);

        return Alert::findOrFail($alertId);
    }

    /**
     * @return array{OPEN: int, ACKNOWLEDGED: int, RESOLVED: int}
     */
    public function countByStatusForOrganization(string $organizationId): array
    {
        $counts = $this->scopedToOrganization($organizationId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'OPEN' => (int) ($counts[AlertStatus::Open->value] ?? 0),
            'ACKNOWLEDGED' => (int) ($counts[AlertStatus::Acknowledged->value] ?? 0),
            'RESOLVED' => (int) ($counts[AlertStatus::Resolved->value] ?? 0),
        ];
    }

    /**
     * @return array<int, Alert>
     */
    public function listRecentForOrganization(string $organizationId, int $limit): array
    {
        // Eager-loaded: DashboardResource now reads reasons off the related
        // Prediction for each recent Alert (RC-3) — same N+1 reasoning as
        // listForOrganization() above.
        return $this->scopedToOrganization($organizationId)
            ->with('prediction')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * @return Builder<Alert>
     */
    private function scopedToOrganization(string $organizationId): Builder
    {
        return Alert::query()
            ->whereHas('prediction.observation', fn ($query) => $query->where('organization_id', $organizationId));
    }
}
