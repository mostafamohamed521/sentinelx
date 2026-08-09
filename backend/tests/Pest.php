<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Alert\Domain\AlertStatus;
use App\Modules\Alert\Infrastructure\Persistence\Alert;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Issues a bearer JWT for a Human User — shared across Auth, Agent, and
 * Observation feature tests to avoid redeclaring the same helper in every
 * file.
 */
function tokenFor(User $user): string
{
    return JWTAuth::fromUser($user);
}

/**
 * Creates an Agent with an ACTIVE API Key hashed from the given raw key,
 * so tests can authenticate as that Agent via the X-API-Key header —
 * shared across Auth and Observation feature tests.
 *
 * @param  array<string, mixed>  $agentState
 * @param  array<string, mixed>  $keyState
 */
function createAgentWithKey(string $rawKey, array $agentState = [], array $keyState = []): Agent
{
    $agent = Agent::factory()->create($agentState);

    ApiKey::factory()->for($agent)->create([
        'key_hash' => hash('sha256', $rawKey),
        ...$keyState,
    ]);

    return $agent;
}

/**
 * A minimal but fully schema-conformant ASES payload, matching
 * 07-api-contract.md §1's own example exactly. `context` and `metadata`
 * overrides are shallow-merged into the defaults; `events`, being a list,
 * is always replaced wholesale when provided — a recursive/keyed merge
 * would silently keep the default event when a test passes `events: []`
 * to exercise the "at least one event" check.
 *
 * @param  array{context?: array<string, mixed>, events?: array<int, mixed>, metadata?: array<string, mixed>}  $overrides
 * @return array<string, mixed>
 */
function validAsesPayload(array $overrides = []): array
{
    return [
        'context' => [
            'framework' => 'CrewAI',
            'agent_version' => '1.2.0',
            'environment' => 'production',
            'execution_start_time' => '2026-07-29T09:59:50Z',
            'execution_finish_time' => '2026-07-29T10:00:00Z',
            ...$overrides['context'] ?? [],
        ],
        'events' => $overrides['events'] ?? [
            [
                'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T09:59:52Z'],
                'payload' => ['url' => 'https://api.example.com/v1/data', 'method' => 'GET'],
            ],
        ],
        'metadata' => [
            'spec_version' => '1.0',
            'sdk_version' => '0.4.1',
            'generated_at' => '2026-07-29T10:00:00Z',
            ...$overrides['metadata'] ?? [],
        ],
    ];
}

/**
 * Builds a full Observation -> Prediction -> Alert chain scoped to the
 * given Organization — shared across Alert feature tests to avoid
 * redeclaring the same three-model chain in every file.
 *
 * @param  array<string, mixed>  $alertState
 * @param  array<string, mixed>  $predictionState
 */
function alertFor(Organization $organization, array $alertState = [], array $predictionState = []): Alert
{
    $observation = Observation::factory()->for($organization)->create();
    $prediction = Prediction::factory()->for($observation)->create($predictionState);

    return Alert::factory()->for($prediction)->create($alertState);
}

/**
 * Same as alertFor(), starting the Alert at a specific AlertStatus — the
 * common shape needed by acknowledge/resolve tests.
 */
function alertWithStatusFor(Organization $organization, AlertStatus $status): Alert
{
    return alertFor($organization, ['status' => $status]);
}
