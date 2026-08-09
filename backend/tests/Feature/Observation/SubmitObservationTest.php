<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('an authenticated agent can submit a well-formed observation', function () {
    $agent = createAgentWithKey('a-valid-raw-secret');
    $payload = validAsesPayload();

    $response = $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.analysis_status', 'PENDING')
        ->assertJsonMissingPath('data.raw_ases_json');

    $observation = Observation::where('agent_id', $agent->id)->firstOrFail();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Pending)
        ->and($observation->organization_id)->toBe($agent->organization_id)
        ->and($observation->raw_ases_json)->toEqual($payload);
});

// === EDGE CASES ===

test('an observation with zero events is rejected with 422', function () {
    createAgentWithKey('a-valid-raw-secret');

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', validAsesPayload(['events' => []]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('an observation with out-of-order event timestamps is rejected with 422', function () {
    createAgentWithKey('a-valid-raw-secret');

    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['url' => 'https://api.example.com', 'method' => 'GET'],
            ],
            [
                'header' => ['event_type' => 'file_access', 'timestamp' => '2026-07-29T09:00:00Z'],
                'payload' => ['path' => '/tmp/file.txt'],
            ],
        ],
    ]);

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('an observation missing a required context field is rejected with 422', function () {
    createAgentWithKey('a-valid-raw-secret');

    $payload = validAsesPayload();
    unset($payload['context']['framework']);

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('a malformed, non-JSON request body is rejected with 400', function () {
    createAgentWithKey('a-valid-raw-secret');

    $response = $this->call('POST', '/api/v1/observations', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_API_KEY' => 'a-valid-raw-secret',
    ], content: '{not valid json');

    $response->assertStatus(400)
        ->assertJsonPath('error.code', 'BAD_REQUEST');
});

test('an event type outside the canonical event dictionary is rejected with 422', function () {
    createAgentWithKey('a-valid-raw-secret');

    $payload = validAsesPayload([
        'events' => [[
            'header' => ['event_type' => 'not_a_real_event_type', 'timestamp' => '2026-07-29T10:00:00Z'],
            'payload' => ['foo' => 'bar'],
        ]],
    ]);

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// === BUSINESS RULES ===

test('organization_id and agent_id fields inside the payload are inert, never used for ownership', function () {
    $agent = createAgentWithKey('a-valid-raw-secret');
    $otherOrganization = Organization::factory()->create();

    $payload = validAsesPayload();
    $payload['organization_id'] = $otherOrganization->id;
    $payload['agent_id'] = 'not-a-real-agent-id';

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', $payload)
        ->assertStatus(202);

    $observation = Observation::where('agent_id', $agent->id)->firstOrFail();

    expect($observation->organization_id)->toBe($agent->organization_id)
        ->and($observation->organization_id)->not->toBe($otherOrganization->id);
});

test('analysis_status is always exactly PENDING immediately after a successful submission', function () {
    createAgentWithKey('a-valid-raw-secret');

    $response = $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', validAsesPayload());

    $response->assertJsonPath('data.analysis_status', 'PENDING');

    expect(Observation::first()->analysis_status)->toBe(AnalysisStatus::Pending);
});

test('there is no PATCH, PUT, or DELETE route for observations', function () {
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/observations'));

    $methods = $routes->flatMap(fn ($route) => $route->methods())->unique()->values()->all();

    expect($methods)->not->toContain('PATCH')
        ->and($methods)->not->toContain('PUT')
        ->and($methods)->not->toContain('DELETE');
});

// === AUTHORIZATION ===

test('a valid JWT with no API Key cannot submit an observation', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->postJson('/api/v1/observations', validAsesPayload())
        ->assertUnauthorized();
});

test('an agent whose api key was just revoked cannot submit an observation', function () {
    createAgentWithKey('a-revoked-secret', keyState: ['status' => ApiKeyStatus::Revoked]);

    $this->withHeader('X-API-Key', 'a-revoked-secret')
        ->postJson('/api/v1/observations', validAsesPayload())
        ->assertUnauthorized();
});

test('an archived agent cannot submit an observation', function () {
    createAgentWithKey('an-archived-agent-secret', agentState: ['status' => AgentStatus::Archived]);

    $this->withHeader('X-API-Key', 'an-archived-agent-secret')
        ->postJson('/api/v1/observations', validAsesPayload())
        ->assertUnauthorized();
});

test('an unauthenticated request cannot submit an observation', function () {
    $this->postJson('/api/v1/observations', validAsesPayload())
        ->assertUnauthorized();
});
