<?php

use App\Modules\Agent\AgentServiceProvider;
use App\Modules\Alert\AlertServiceProvider;
use App\Modules\Analysis\AnalysisServiceProvider;
use App\Modules\Audit\AuditServiceProvider;
use App\Modules\Authentication\AuthServiceProvider;
use App\Modules\Observation\ObservationServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    AgentServiceProvider::class,
    AuthServiceProvider::class,
    ObservationServiceProvider::class,
    AnalysisServiceProvider::class,
    AlertServiceProvider::class,
    AuditServiceProvider::class,
];
