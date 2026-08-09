<?php

namespace App\Modules\Analysis\Infrastructure\Queue;

use App\Modules\Analysis\Application\ClaimPendingObservationsAction;
use Illuminate\Console\Command;

/**
 * Tier 1 of the pipeline (03-processing-pipeline.md §3) — thin, delegates
 * entirely to ClaimPendingObservationsAction. Scheduled in routes/console.php.
 */
class PollPendingObservationsCommand extends Command
{
    protected $signature = 'analysis:poll-pending-observations {--limit=}';

    protected $description = 'Claims a batch of PENDING Observations and dispatches an analysis Job for each.';

    public function handle(ClaimPendingObservationsAction $action): int
    {
        // PERF-002: the platform-wide, global throughput ceiling — see
        // config/analysis.php and docs/05-analysis/03-processing-pipeline.md
        // §"Capacity". --limit still overrides it explicitly when passed.
        $limit = (int) ($this->option('limit') ?? config('analysis.poll_batch_limit'));

        $claimed = $action->handle($limit);

        $this->info("Claimed {$claimed} Observation(s) for analysis.");

        return self::SUCCESS;
    }
}
