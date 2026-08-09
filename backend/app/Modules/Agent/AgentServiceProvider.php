<?php

namespace App\Modules\Agent;

use App\Modules\Agent\Application\Contracts\AgentLookupContract;
use App\Modules\Agent\Application\Contracts\AgentSummaryContract;
use App\Modules\Agent\Infrastructure\Persistence\AgentRepository;
use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AgentLookupContract::class, AgentRepository::class);
        $this->app->bind(AgentSummaryContract::class, AgentRepository::class);
    }
}
