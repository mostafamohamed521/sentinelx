<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Artisan;

test('the database seeder runs end to end and every observation keeps its agent\'s organization', function () {
    Artisan::call('db:seed');

    expect(Organization::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(0)
        ->and(Agent::count())->toBeGreaterThan(0)
        ->and(ApiKey::count())->toBeGreaterThan(0)
        ->and(Observation::count())->toBeGreaterThan(0)
        ->and(Prediction::count())->toBeGreaterThan(0)
        ->and(Alert::count())->toBeGreaterThan(0);

    $mismatched = Observation::query()
        ->join('agents', 'agents.id', '=', 'observations.agent_id')
        ->whereColumn('observations.organization_id', '!=', 'agents.organization_id')
        ->count();

    expect($mismatched)->toBe(0);
});
