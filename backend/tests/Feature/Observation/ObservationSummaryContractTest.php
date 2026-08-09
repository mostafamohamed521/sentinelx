<?php

use App\Modules\Observation\Application\Contracts\ObservationSummaryContract;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('countForOrganizationSince counts only observations received on or after the given timestamp', function () {
    $organization = Organization::factory()->create();
    Observation::factory()->for($organization)->create(['received_at' => now()->subDays(5)]);
    Observation::factory()->for($organization)->create(['received_at' => now()->subDays(40)]);

    $count = app(ObservationSummaryContract::class)->countForOrganizationSince($organization->id, now()->subDays(30));

    expect($count)->toBe(1);
});

test('listRecentForOrganization orders by received_at descending', function () {
    $organization = Organization::factory()->create();
    $older = Observation::factory()->for($organization)->create(['received_at' => now()->subHours(3)]);
    $newer = Observation::factory()->for($organization)->create(['received_at' => now()->subMinutes(10)]);

    $result = app(ObservationSummaryContract::class)->listRecentForOrganization($organization->id, 5);

    expect($result)->toHaveCount(2)
        ->and($result[0]->id)->toBe($newer->id)
        ->and($result[1]->id)->toBe($older->id);
});

// === EDGE CASE ===

test('listRecentForOrganization respects the given limit', function () {
    $organization = Organization::factory()->create();
    Observation::factory(8)->for($organization)->create();

    expect(app(ObservationSummaryContract::class)->listRecentForOrganization($organization->id, 5))->toHaveCount(5);
});

test('countForOrganizationSince returns 0 for an organization with no observations', function () {
    $organization = Organization::factory()->create();

    expect(app(ObservationSummaryContract::class)->countForOrganizationSince($organization->id, now()->subDays(30)))->toBe(0);
});

// === DATA ISOLATION ===

test('observation summary methods never include another organizations observations', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    Observation::factory(3)->for($organizationB)->create();

    expect(app(ObservationSummaryContract::class)->countForOrganizationSince($organizationA->id, now()->subDays(30)))->toBe(0)
        ->and(app(ObservationSummaryContract::class)->listRecentForOrganization($organizationA->id, 5))->toBe([]);
});
