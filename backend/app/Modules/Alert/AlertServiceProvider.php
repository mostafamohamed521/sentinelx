<?php

namespace App\Modules\Alert;

use App\Modules\Alert\Application\Contracts\AlertSummaryContract;
use App\Modules\Alert\Infrastructure\Listeners\EvaluateAlertPolicyOnPredictionStored;
use App\Modules\Alert\Infrastructure\Persistence\AlertRepository;
use App\Modules\Analysis\Domain\Events\PredictionStored;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AlertServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AlertSummaryContract::class, AlertRepository::class);
    }

    public function boot(): void
    {
        // The Alert module reacts to the Analysis module's own event —
        // Analysis never imports this listener, never calls it, never
        // knows it exists. See 05-cross-module-boundaries.md §2.
        Event::listen(PredictionStored::class, EvaluateAlertPolicyOnPredictionStored::class);
    }
}
