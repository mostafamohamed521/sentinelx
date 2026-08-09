<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tier 1 of the Analysis pipeline (docs/backend/analysis/03-processing-pipeline.md
// §3) — claims PENDING Observations and dispatches AnalyzeObservationJob for
// each. withoutOverlapping() so a slow run never lets a second Poller start
// while the first is still claiming/dispatching.
Schedule::command('analysis:poll-pending-observations')
    ->everyMinute()
    ->withoutOverlapping();
