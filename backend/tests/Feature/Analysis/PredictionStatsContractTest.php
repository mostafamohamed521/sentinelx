<?php

use App\Modules\Analysis\Application\Contracts\PredictionStatsContract;
use App\Modules\Analysis\Domain\Verdict;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('verdictDistributionForOrganization reflects the actual verdict counts, SAFE included', function () {
    $organization = Organization::factory()->create();
    Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Safe]);
    Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Safe]);
    Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Suspicious]);
    Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Malicious]);

    $distribution = app(PredictionStatsContract::class)->verdictDistributionForOrganization($organization->id);

    expect($distribution)->toBe(['SAFE' => 2, 'SUSPICIOUS' => 1, 'MALICIOUS' => 1]);
});

// === EDGE CASE ===

test('verdictDistributionForOrganization includes a zero for a verdict that never occurred', function () {
    $organization = Organization::factory()->create();
    Prediction::factory()->for(Observation::factory()->for($organization))->create(['verdict' => Verdict::Safe]);

    $distribution = app(PredictionStatsContract::class)->verdictDistributionForOrganization($organization->id);

    expect($distribution)->toBe(['SAFE' => 1, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0]);
});

test('verdictDistributionForOrganization returns all-zero keys for an organization with no predictions', function () {
    $organization = Organization::factory()->create();

    $distribution = app(PredictionStatsContract::class)->verdictDistributionForOrganization($organization->id);

    expect($distribution)->toBe(['SAFE' => 0, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0]);
});

// === DATA ISOLATION ===

test('verdictDistributionForOrganization never includes another organizations predictions', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    Prediction::factory()->for(Observation::factory()->for($organizationB))->create(['verdict' => Verdict::Malicious]);

    $distribution = app(PredictionStatsContract::class)->verdictDistributionForOrganization($organizationA->id);

    expect($distribution)->toBe(['SAFE' => 0, 'SUSPICIOUS' => 0, 'MALICIOUS' => 0]);
});
