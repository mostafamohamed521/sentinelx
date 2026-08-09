<?php

namespace App\Modules\Analysis\Infrastructure\Queue;

use App\Modules\Analysis\Application\AnalyzeObservationAction;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin — delegates entirely to AnalyzeObservationAction. Retry/backoff
 * policy is an engineering default, not a frozen business rule — see
 * adr/ADR-003-ml-failure-retry-then-fail.md.
 *
 * The failed() hook is the single place that guarantees an Observation
 * never sits in PROCESSING forever after retries are exhausted — the
 * Action itself never calls markFailed() for a transport failure, so
 * without this hook a genuinely down ML Engine would leave the Observation
 * stuck. This is a safety net, not a duplicate: InvalidMlResponseException
 * is already resolved inside the Action without ever throwing, so failed()
 * never double-writes for that case.
 */
class AnalyzeObservationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function __construct(
        public readonly string $observationId,
        public readonly string $organizationId,
    ) {}

    public function handle(AnalyzeObservationAction $action): void
    {
        $action->handle($this->observationId, $this->organizationId);
    }

    public function failed(?Throwable $exception): void
    {
        // ERROR-005: brings this path's observability in line with the
        // sibling InvalidMlResponseException path (AnalyzeObservationAction's
        // own Log::warning) -- both failure kinds now produce an explicit,
        // similarly-structured log entry for the same terminal FAILED
        // outcome, rather than this one relying solely on Laravel's default
        // job-failure reporting (still separately emitted; this is
        // additional, not a replacement — see ADR-003, retry visibility
        // still matters). `metric` is OBS-003's greppable tag for computing
        // an ML failure rate from log lines alone.
        Log::warning('Analysis failed: ML communication error (retries exhausted).', [
            'metric' => 'ml_call_failed',
            'observation_id' => $this->observationId,
            'reason' => $exception?->getMessage(),
        ]);

        app(ObservationRepository::class)->markFailed($this->observationId, now());
    }
}
