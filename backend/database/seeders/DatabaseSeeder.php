<?php

namespace Database\Seeders;

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Follows the documented dependency order: organizations -> users/agents ->
     * api_keys -> observations -> predictions -> alerts. Model events stay
     * active (no WithoutModelEvents) so the ApiKey "single ACTIVE key"
     * business rule still runs during seeding.
     */
    public function run(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        User::factory()->owner()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Test Owner',
            'email' => 'owner@test-company.example',
        ]);

        User::factory(2)->for($organization)->create();

        Agent::factory(5)
            ->for($organization)
            ->create()
            ->each(function (Agent $agent) {
                ApiKey::factory()->for($agent)->create();
                Observation::factory(3)->for($agent)->create();
            });

        // Built from the Prediction/Alert side, not via Observation::has() —
        // the Observation module has no relation onto `predictions` at all
        // (Observation must never depend on Analysis; see
        // 05-cross-module-boundaries.md §1 of the Observation docs).
        $analyzedObservation = Observation::factory()
            ->for($organization)
            ->for(Agent::factory()->for($organization))
            ->completed()
            ->create();

        Alert::factory()
            ->for(Prediction::factory()->malicious()->for($analyzedObservation))
            ->create();
    }
}
