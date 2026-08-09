<?php

namespace App\Modules\Analysis\Infrastructure\Queue;

use App\Modules\Analysis\Application\RetryFailedObservationsAction;
use Illuminate\Console\Command;

/**
 * STATE-004/FAILURE-003 — operator/CLI tool only, deliberately not a REST
 * endpoint (see RetryFailedObservationsAction's own doc-block). Requeues a
 * FAILED Observation back to PENDING so the next Poller run
 * (PollPendingObservationsCommand) picks it up for re-analysis.
 */
class RetryFailedObservationsCommand extends Command
{
    protected $signature = 'analysis:retry-failed
        {observationId? : A specific FAILED Observation ID to requeue}
        {--all : Requeue every currently FAILED Observation, platform-wide}';

    protected $description = 'Requeues one or all FAILED Observation(s) back to PENDING for re-analysis.';

    public function handle(RetryFailedObservationsAction $action): int
    {
        $observationId = $this->argument('observationId');

        if (! $observationId && ! $this->option('all')) {
            $this->error('Provide an Observation ID, or pass --all to requeue every FAILED Observation.');

            return self::FAILURE;
        }

        $retried = $action->handle($observationId);

        $this->info("Requeued {$retried} Observation(s) for re-analysis.");

        return self::SUCCESS;
    }
}
