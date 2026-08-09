<?php

use App\Modules\Agent\Domain\Exceptions\AgentNotFoundException;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Authentication\Identity\Domain\AuthenticationFailedException;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// === ERROR-001: every FormRequest failure returns the identical nested envelope ===

test('every FormRequest validation failure returns the identical nested VALIDATION_ERROR envelope, with a request_id', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $agent = Agent::factory()->for($organization)->create();
    $auth = ['Authorization' => 'Bearer '.tokenFor($owner)];

    $cases = [
        'RegisterRequest' => fn () => $this->postJson('/api/v1/auth/register', [
            'organization_name' => '', 'full_name' => '', 'email' => 'not-an-email',
            'password' => 'short', 'password_confirmation' => 'does-not-match',
        ]),
        'LoginRequest' => fn () => $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email']),
        'ChangePasswordRequest' => fn () => $this->withHeaders($auth)->postJson('/api/v1/me/change-password', [
            'current_password' => '', 'new_password' => 'short', 'new_password_confirmation' => 'nope',
        ]),
        'UpdateProfileRequest' => fn () => $this->withHeaders($auth)->patchJson('/api/v1/me', ['email' => 'not-an-email']),
        'UpdateOrganizationRequest' => fn () => $this->withHeaders($auth)->patchJson('/api/v1/organization', ['name' => '', 'slug' => 'nope']),
        'StoreAgentRequest' => fn () => $this->withHeaders($auth)->postJson('/api/v1/agents', []),
        'UpdateAgentRequest' => fn () => $this->withHeaders($auth)->patchJson("/api/v1/agents/{$agent->id}", ['organization_id' => 'nope']),
    ];

    foreach ($cases as $name => $makeRequest) {
        $response = $makeRequest();

        expect($response->getStatusCode())->toBe(422, "{$name} did not return 422")
            ->and($response->json('error.code'))->toBe('VALIDATION_ERROR', "{$name}'s error.code")
            ->and($response->json('error.message'))->toBeString()
            ->and($response->json('error.details'))->toBeArray()->not->toBeEmpty("{$name}'s error.details")
            ->and($response->json('error.request_id'))->toBeString()->not->toBeEmpty("{$name}'s error.request_id")
            ->and($response->headers->get('X-Request-Id'))->toBe($response->json('error.request_id'), "{$name}'s X-Request-Id header");
    }
});

// === OBS-004: routine business exceptions are excluded from default reporting ===

test('routine business exceptions are excluded from Laravel\'s default exception reporting, unlike a genuine unexpected exception', function () {
    $handler = app(ExceptionHandler::class);

    expect($handler->shouldReport(new AgentNotFoundException))->toBeFalse()
        ->and($handler->shouldReport(new AuthenticationFailedException))->toBeFalse()
        ->and($handler->shouldReport(new RuntimeException('something genuinely unexpected broke')))->toBeTrue();
});

// === FAILURE-002: a database failure never leaks its own detail ===

test('a QueryException renders a clean, non-leaking SERVICE_UNAVAILABLE 503', function () {
    $handler = app(ExceptionHandler::class);
    $request = Request::create('/api/v1/agents', 'GET');

    $sensitiveSql = 'select * from users where email = ? -- sensitive_column_name';
    $exception = new QueryException(
        'pgsql',
        $sensitiveSql,
        [],
        new RuntimeException('SQLSTATE[08006] connection to server failed: password authentication failed for user "prod_app_secret"'),
    );

    $response = $handler->render($request, $exception);
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(503);
    $decoded = json_decode($body, true);
    expect($decoded['error']['code'])->toBe('SERVICE_UNAVAILABLE')
        ->and($body)->not->toContain('prod_app_secret')
        ->and($body)->not->toContain($sensitiveSql)
        ->and($body)->not->toContain('QueryException')
        ->and($body)->not->toContain('.php');
});

test('an undefined route (404) is left alone by the Throwable catch-all, not flattened into SERVICE_UNAVAILABLE', function () {
    $this->getJson('/api/v1/this-route-does-not-exist')
        ->assertNotFound()
        ->assertJsonMissingPath('error.code');
});

// === ERROR-007: a generic, unhandled exception never leaks file/line/trace,
// regardless of app.debug — the general case, independent of FAILURE-002's
// QueryException-specific fix ===

test('a generic unhandled exception never leaks file paths, line numbers, or stack trace content, even with app.debug true', function () {
    config(['app.debug' => true]);

    Route::middleware('api')->get('/api/v1/__test-only-unhandled-exception', function () {
        throw new TypeError('deliberately unhandled, for ERROR-007 regression coverage');
    });

    $response = $this->getJson('/api/v1/__test-only-unhandled-exception');
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(503)
        ->and($body)->not->toContain('TypeError')
        ->and($body)->not->toContain(__FILE__)
        ->and($body)->not->toContain('.php')
        ->and($body)->not->toContain('Stack trace');
});
