<?php

use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

// RateLimiter::for() registrations live on the container, which (unlike the
// database) is not reset between tests in the same process — overriding a
// production limiter name here would otherwise leak into every later test
// that hits these route groups. Restore the real AppServiceProvider
// definitions after each test in this file.
afterEach(function () {
    RateLimiter::for('observation-ingestion', fn (Request $request) => Limit::perMinute(300)
        ->by((string) ($request->user('agent')?->id ?? $request->ip()))
    );

    RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
        ->by((string) ($request->user('api')?->id ?? $request->ip()))
    );
});

// === SECURITY-002: every v1 route has a named, explicit rate-limiting policy ===

test('the Observation ingestion route is rate-limited per-Agent, the system\'s highest-volume endpoint', function () {
    RateLimiter::for('observation-ingestion', fn () => Limit::perMinute(1)->by('test-agent'));

    $rawKey = 'sk_live_rate_limit_test';
    createAgentWithKey($rawKey);

    $this->withHeader('X-Api-Key', $rawKey)->postJson('/api/v1/observations', []);
    $response = $this->withHeader('X-Api-Key', $rawKey)->postJson('/api/v1/observations', []);

    $response->assertStatus(429);
});

test('the general v1 auth:api route group is rate-limited per-Human-user', function () {
    RateLimiter::for('api', fn () => Limit::perMinute(1)->by('test-user'));

    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $auth = ['Authorization' => 'Bearer '.tokenFor($owner)];

    $this->withHeaders($auth)->getJson('/api/v1/agents');
    $response = $this->withHeaders($auth)->getJson('/api/v1/agents');

    $response->assertStatus(429);
});

test('no v1 route relies on an unnamed, ad hoc numeric throttle', function () {
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1'))
        ->flatMap(fn ($route) => $route->middleware());

    $numericThrottles = $routes->filter(fn ($middleware) => preg_match('/^throttle:\d/', (string) $middleware));

    expect($numericThrottles)->toBeEmpty();
});
