<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Http;

// === HAPPY PATH (full end-to-end: submit -> poll -> job -> GET) ===

test('an observation moves PENDING to COMPLETED and GET /observations/{id} returns the populated prediction', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'MALICIOUS',
        'risk_score' => 91,
        'confidence' => 0.97,
        'summary' => 'Credential exfiltration attempt detected.',
        'model_version' => 'sentinelx-ml-1.4.2',
        'reasons' => ['known malicious destination'],
        'evidence' => [['type' => 'event_reference', 'sequence' => 1]],
    ])]);

    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->for($agent)->create();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Pending);

    // Tier 1 — Poller (sync queue, so the dispatched Job runs inline)
    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/observations/{$observation->id}")
        ->assertOk()
        ->assertJsonPath('data.analysis_status', AnalysisStatus::Completed->value)
        ->assertJsonPath('data.prediction.verdict', 'MALICIOUS')
        ->assertJsonPath('data.prediction.risk_score', 91)
        ->assertJsonPath('data.prediction.model_version', 'sentinelx-ml-1.4.2')
        ->assertJsonMissingPath('data.prediction.prediction_json');
});

test('an observation whose ML analysis fails still returns prediction null via the API, never a partially-written row', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(['verdict' => 'INVALID_VALUE'])]);

    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Failed);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/observations/{$observation->id}")
        ->assertOk()
        ->assertJsonPath('data.analysis_status', AnalysisStatus::Failed->value)
        ->assertJsonPath('data.prediction', null);
});

// === AUTHORIZATION ===

test('a Member can view a COMPLETED observations prediction data exactly as an Owner can', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'SAFE',
        'risk_score' => 4,
        'confidence' => 0.99,
        'summary' => 'Routine activity.',
        'model_version' => 'sentinelx-ml-1.4.2',
    ])]);

    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson("/api/v1/observations/{$observation->id}")
        ->assertOk()
        ->assertJsonPath('data.prediction.verdict', 'SAFE');
});

test('an Agent (API Key) can never view a prediction — Agents have no read access to observations at all', function () {
    $rawKey = 'sk_live_test_analysis';
    $agent = createAgentWithKey($rawKey);
    $observation = Observation::factory()->for($agent->organization)->for($agent)->completed()->create();

    $this->withHeader('X-Api-Key', $rawKey)
        ->getJson("/api/v1/observations/{$observation->id}")
        ->assertUnauthorized();
});

// === DATA ISOLATION ===

test('a completed prediction from another organization is never visible — 404, not leaked data', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'MALICIOUS',
        'risk_score' => 88,
        'confidence' => 0.9,
        'summary' => 'Should never be visible cross-tenant.',
        'model_version' => 'sentinelx-ml-1.4.2',
    ])]);

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $observationB = Observation::factory()->for($organizationB)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/observations/{$observationB->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});
