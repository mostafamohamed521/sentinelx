<?php

namespace App\Modules\Observation\Infrastructure\Persistence;

use App\Modules\Observation\Application\Contracts\ObservationLookupContract;
use App\Modules\Observation\Application\Contracts\ObservationSummaryContract;
use App\Modules\Observation\Domain\AnalysisStatus;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The only place inside the Observation module that touches the
 * `observations` table directly. Every read is scoped to organization_id —
 * see 06-authorization.md §4 ("Scoped Queries", 404 not 403 for
 * cross-tenant). The three mark*() methods are exposed now for Analysis
 * (Stage 4) to consume later — see 05-cross-module-boundaries.md §2.
 */
class ObservationRepository implements ObservationLookupContract, ObservationSummaryContract
{
    public function create(array $attributes): Observation
    {
        return Observation::create($attributes);
    }

    public function findById(string $observationId, string $organizationId): ?Observation
    {
        return Observation::query()
            ->where('organization_id', $organizationId)
            ->where('id', $observationId)
            ->first();
    }

    public function listForOrganization(
        string $organizationId,
        ?string $agentId,
        ?AnalysisStatus $status,
        int $perPage,
        int $page,
    ): LengthAwarePaginator {
        return Observation::query()
            ->where('organization_id', $organizationId)
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))
            ->when($status, fn ($query) => $query->where('analysis_status', $status))
            ->orderByDesc('received_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Not yet called by anything — Analysis (Stage 4) will call this once a
     * queued Observation begins processing. See 05-cross-module-boundaries.md §2.
     */
    public function markProcessing(string $observationId): void
    {
        Observation::query()->where('id', $observationId)->update([
            'analysis_status' => AnalysisStatus::Processing,
            'processing_started_at' => now(),
        ]);
    }

    /**
     * Not yet called by anything — Analysis (Stage 4) will call this once
     * ML analysis completes successfully. See 05-cross-module-boundaries.md §2.
     */
    public function markCompleted(string $observationId, Carbon $processedAt): void
    {
        Observation::query()->where('id', $observationId)->update([
            'analysis_status' => AnalysisStatus::Completed,
            'processed_at' => $processedAt,
        ]);
    }

    /**
     * Not yet called by anything — Analysis (Stage 4) will call this if ML
     * analysis fails. See 05-cross-module-boundaries.md §2.
     */
    public function markFailed(string $observationId, Carbon $processedAt): void
    {
        Observation::query()->where('id', $observationId)->update([
            'analysis_status' => AnalysisStatus::Failed,
            'processed_at' => $processedAt,
        ]);
    }

    public function findByIdForOrganization(string $observationId, string $organizationId): ?Observation
    {
        return $this->findById($observationId, $organizationId);
    }

    /**
     * Selects the oldest PENDING Observations and atomically transitions
     * them to PROCESSING in the same transaction — the SELECT and the
     * status flip must never be split into two separate steps, or two
     * concurrent Poller runs could both claim the same row. See
     * docs/backend/analysis/03-processing-pipeline.md §4 and
     * 08-implementation-roadmap.md §3. Not part of ObservationLookupContract
     * (that interface is read-only) — called directly by Analysis's
     * ClaimPendingObservationsAction, the same direct-concrete-repository
     * pattern already used for touchLastSeen().
     *
     * @return array<int, Observation>
     */
    public function claimNextPendingBatch(int $limit): array
    {
        return DB::transaction(function () use ($limit) {
            $observations = Observation::query()
                ->where('analysis_status', AnalysisStatus::Pending)
                ->orderBy('received_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($observations->isEmpty()) {
                return [];
            }

            $claimedAt = now();

            Observation::query()
                ->whereIn('id', $observations->pluck('id'))
                ->update([
                    'analysis_status' => AnalysisStatus::Processing,
                    'processing_started_at' => $claimedAt,
                ]);

            return $observations
                ->each(function (Observation $observation) use ($claimedAt) {
                    $observation->analysis_status = AnalysisStatus::Processing;
                    $observation->processing_started_at = $claimedAt;
                })
                ->all();
        });
    }

    /**
     * STATE-004/FAILURE-003: the only writer anywhere that moves a FAILED
     * Observation back toward re-analysis — a deliberate, operator-triggered
     * recovery path (see
     * App\Modules\Analysis\Infrastructure\Queue\RetryFailedObservationsCommand),
     * not an automated retry. Guarded by the WHERE clause so retrying an
     * Observation that isn't actually FAILED (already retried, or never
     * failed at all) is a safe no-op rather than clobbering a row mid-flight.
     */
    public function retryFailed(string $observationId): bool
    {
        return Observation::query()
            ->where('id', $observationId)
            ->where('analysis_status', AnalysisStatus::Failed)
            ->update([
                'analysis_status' => AnalysisStatus::Pending,
                'processing_started_at' => null,
                'processed_at' => null,
            ]) > 0;
    }

    /**
     * Bulk variant of retryFailed() — every currently FAILED Observation,
     * platform-wide. See retryFailed()'s own doc-block.
     */
    public function retryAllFailed(): int
    {
        return Observation::query()
            ->where('analysis_status', AnalysisStatus::Failed)
            ->update([
                'analysis_status' => AnalysisStatus::Pending,
                'processing_started_at' => null,
                'processed_at' => null,
            ]);
    }

    public function countForOrganizationSince(string $organizationId, DateTimeInterface $since): int
    {
        return Observation::query()
            ->where('organization_id', $organizationId)
            ->where('received_at', '>=', $since)
            ->count();
    }

    /**
     * A thin wrapper over the existing listForOrganization(), without
     * pagination metadata — see docs/backend/dashboard/04-aggregation-contracts.md §3.
     *
     * @return array<int, Observation>
     */
    public function listRecentForOrganization(string $organizationId, int $limit): array
    {
        return $this->listForOrganization($organizationId, agentId: null, status: null, perPage: $limit, page: 1)
            ->items();
    }
}
