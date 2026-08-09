<?php

use App\Modules\Alert\Application\Contracts\AlertSummaryContract;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('countByStatusForOrganization returns accurate counts scoped to the organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    alertWithStatusFor($organizationA, AlertStatus::Open);
    alertWithStatusFor($organizationA, AlertStatus::Open);
    alertWithStatusFor($organizationA, AlertStatus::Resolved);
    alertWithStatusFor($organizationB, AlertStatus::Open);

    $counts = app(AlertSummaryContract::class)->countByStatusForOrganization($organizationA->id);

    expect($counts)->toBe(['OPEN' => 2, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 1]);
});

test('listRecentForOrganization orders by created_at descending, scoped to the organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    $older = alertWithStatusFor($organizationA, AlertStatus::Open);
    $older->forceFill(['created_at' => now()->subHours(2)])->save();
    $newer = alertWithStatusFor($organizationA, AlertStatus::Open);
    $newer->forceFill(['created_at' => now()->subMinutes(5)])->save();
    alertWithStatusFor($organizationB, AlertStatus::Open);

    $result = app(AlertSummaryContract::class)->listRecentForOrganization($organizationA->id, 5);

    expect($result)->toHaveCount(2)
        ->and($result[0]->id)->toBe($newer->id)
        ->and($result[1]->id)->toBe($older->id);
});

// === EDGE CASE ===

test('countByStatusForOrganization returns all zeros for an organization with no alerts', function () {
    $organization = Organization::factory()->create();

    $counts = app(AlertSummaryContract::class)->countByStatusForOrganization($organization->id);

    expect($counts)->toBe(['OPEN' => 0, 'ACKNOWLEDGED' => 0, 'RESOLVED' => 0]);
});

test('listRecentForOrganization respects the given limit', function () {
    $organization = Organization::factory()->create();

    for ($i = 0; $i < 7; $i++) {
        alertWithStatusFor($organization, AlertStatus::Open);
    }

    expect(app(AlertSummaryContract::class)->listRecentForOrganization($organization->id, 5))->toHaveCount(5);
});

test('listRecentForOrganization returns an empty array for an organization with no alerts', function () {
    $organization = Organization::factory()->create();

    expect(app(AlertSummaryContract::class)->listRecentForOrganization($organization->id, 5))->toBe([]);
});
