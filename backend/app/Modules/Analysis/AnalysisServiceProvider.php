<?php

namespace App\Modules\Analysis;

use App\Modules\Analysis\Application\Contracts\PredictionLookupContract;
use App\Modules\Analysis\Application\Contracts\PredictionStatsContract;
use App\Modules\Analysis\Infrastructure\Persistence\PredictionRepository;
use Illuminate\Support\ServiceProvider;

class AnalysisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PredictionLookupContract::class, PredictionRepository::class);
        $this->app->bind(PredictionStatsContract::class, PredictionRepository::class);
    }
}
