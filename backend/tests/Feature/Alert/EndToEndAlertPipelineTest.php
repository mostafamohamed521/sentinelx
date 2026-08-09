<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Support\Facades\Http;

// === HAPPY PATH (full end-to-end: submit -> poll -> analyze -> alert appears) ===

test('an observation whose analysis yields MALICIOUS produces a real, visible Alert', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'MALICIOUS',
        'risk_score' => 92,
        'confidence' => 0.95,
        'summary' => 'Known exfiltration pattern.',
        'model_version' => 'sentinelx-ml-1.4.2',
    ])]);

    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $observation = Observation::factory()->for($organization)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/alerts')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $alertId = $response->json('data.0.id');

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/alerts/{$alertId}")
        ->assertOk()
        ->assertJsonPath('data.severity', 'CRITICAL')
        ->assertJsonPath('data.status', 'OPEN')
        ->assertJsonPath('data.prediction.verdict', 'MALICIOUS')
        ->assertJsonPath('data.observation.id', $observation->id);
});

test('an observation whose analysis yields SAFE never produces an Alert', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response([
        'verdict' => 'SAFE',
        'risk_score' => 2,
        'confidence' => 0.99,
        'summary' => 'Routine activity.',
        'model_version' => 'sentinelx-ml-1.4.2',
    ])]);

    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    Observation::factory()->for($organization)->create();

    $this->artisan('analysis:poll-pending-observations')->assertSuccessful();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/alerts')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
