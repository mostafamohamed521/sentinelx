<?php

use App\Modules\Analysis\Application\AnalyzeObservationAction;
use App\Modules\Analysis\Domain\Exceptions\MLCommunicationException;
use App\Modules\Analysis\Domain\Exceptions\MLConfigurationException;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use App\Modules\Observation\Infrastructure\Persistence\ObservationRepository;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function fakeMlResponse(array $overrides = []): array
{
    return [
        'verdict' => 'SUSPICIOUS',
        'risk_score' => 62,
        'confidence' => 0.78,
        'summary' => 'Unusual outbound network call following a file read outside the expected working directory.',
        'model_version' => 'sentinelx-ml-1.4.2',
        'reasons' => ['unexpected destination host'],
        'evidence' => [['type' => 'event_reference', 'sequence' => 1]],
        ...$overrides,
    ];
}

// === HAPPY PATH ===

test('a successful ML response creates a matching Prediction and marks the observation COMPLETED', function () {
    Http::preventStrayRequests();
    $response = fakeMlResponse();
    Http::fake(['*/analyze' => Http::response($response)]);

    $observation = Observation::factory()->create();
    app(ObservationRepository::class)->markProcessing($observation->id);

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();
    $prediction = Prediction::where('observation_id', $observation->id)->firstOrFail();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($observation->processed_at)->not->toBeNull()
        ->and($prediction->verdict->value)->toBe($response['verdict'])
        ->and((float) $prediction->confidence)->toBe($response['confidence'])
        ->and($prediction->risk_score)->toBe($response['risk_score'])
        ->and($prediction->summary)->toBe($response['summary'])
        ->and($prediction->model_version)->toBe($response['model_version'])
        ->and($prediction->prediction_json)->toBe($response);
});

test('the ML request sends raw_ases_json unmodified plus an empty analysis_options object', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(fakeMlResponse())]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    Http::assertSent(function (HttpClientRequest $request) use ($observation) {
        $body = $request->data();

        // Cast to array for the comparison: MLClient sends (object) [] (not a
        // bare []) so it serializes as a JSON object over real HTTP, not a
        // JSON array -- Http::fake() preserves that original PHP value
        // unconverted, so it arrives here as a stdClass, not a re-decoded
        // array. See MLClient.php's own comment on this exact cast.
        return (array) $body['analysis_options'] === []
            && $body['observation']['id'] === $observation->id
            && $body['observation']['context'] === $observation->raw_ases_json['context']
            && $body['observation']['events'] === $observation->raw_ases_json['events'];
    });
});

test('the ML request carries a non-empty X-Request-Id header', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(fakeMlResponse())]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    Http::assertSent(fn (HttpClientRequest $request) => filled($request->header('X-Request-Id')[0] ?? null));
});

test('the ML request carries a Bearer token matching the configured ML_SERVICE_TOKEN (SECURITY-004)', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(fakeMlResponse())]);
    Config::set('services.ml_engine.token', 'the-configured-secret');

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    Http::assertSent(fn (HttpClientRequest $request) => $request->header('Authorization')[0] === 'Bearer the-configured-secret');
});

test('MLClient fails loudly with MLConfigurationException when ML_SERVICE_TOKEN is unset, instead of sending an unauthenticated request (SECURITY-004)', function () {
    Http::preventStrayRequests();
    Config::set('services.ml_engine.token', null);

    $observation = Observation::factory()->create();

    expect(fn () => app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id))
        ->toThrow(MLConfigurationException::class);

    Http::assertNothingSent();
});

test('a MALICIOUS verdict (the previously-unreachable vocabulary member) round-trips into a stored Prediction (CONTRACT-003)', function () {
    Http::preventStrayRequests();
    $response = fakeMlResponse(['verdict' => 'MALICIOUS', 'risk_score' => 96]);
    Http::fake(['*/analyze' => Http::response($response)]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();
    $prediction = Prediction::where('observation_id', $observation->id)->firstOrFail();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($prediction->verdict->value)->toBe('MALICIOUS');
});

// === EDGE CASE ===

test('an ML response missing verdict marks the observation FAILED with no Prediction row', function () {
    Http::preventStrayRequests();
    $response = fakeMlResponse();
    unset($response['verdict']);
    Http::fake(['*/analyze' => Http::response($response)]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Failed)
        ->and(Prediction::where('observation_id', $observation->id)->exists())->toBeFalse();
});

test('an invalid ML response logs a warning with the observation id and the validation reason (ERROR-002)', function () {
    Log::spy();
    Http::preventStrayRequests();
    $response = fakeMlResponse();
    unset($response['verdict']);
    Http::fake(['*/analyze' => Http::response($response)]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context = []) => $message === 'Analysis failed: ML response failed contract validation.'
            && ($context['observation_id'] ?? null) === $observation->id
            && filled($context['reason'] ?? null)
        );
});

test('an ML response with risk_score outside 0-100 marks the observation FAILED with no Prediction row', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(fakeMlResponse(['risk_score' => 250]))]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Failed)
        ->and(Prediction::where('observation_id', $observation->id)->exists())->toBeFalse();
});

test('an ML engine connection failure throws, leaving the observation PROCESSING for the Job to retry', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::failedConnection()]);

    $observation = Observation::factory()->create();
    app(ObservationRepository::class)->markProcessing($observation->id);

    expect(fn () => app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id))
        ->toThrow(MLCommunicationException::class);

    $observation->refresh();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Processing)
        ->and(Prediction::where('observation_id', $observation->id)->exists())->toBeFalse();
});

test('a 5xx response from the ML engine throws MLCommunicationException', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(['error' => 'internal error'], 500)]);

    $observation = Observation::factory()->create();

    expect(fn () => app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id))
        ->toThrow(MLCommunicationException::class);
});

// === BUSINESS RULE ===

test('analysis_options is always sent as an empty object, never invented fields', function () {
    Http::preventStrayRequests();
    Http::fake(['*/analyze' => Http::response(fakeMlResponse())]);

    $observation = Observation::factory()->create();

    app(AnalyzeObservationAction::class)->handle($observation->id, $observation->organization_id);

    Http::assertSent(fn (HttpClientRequest $request) => (array) $request->data()['analysis_options'] === []);
});
