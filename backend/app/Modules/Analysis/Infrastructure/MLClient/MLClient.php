<?php

namespace App\Modules\Analysis\Infrastructure\MLClient;

use App\Modules\Analysis\Domain\Exceptions\MLCommunicationException;
use App\Modules\Analysis\Domain\Exceptions\MLConfigurationException;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The plain HTTP wrapper around the ML Engine's /analyze endpoint — see
 * 04-ml-client-contract.md §1-3. Sends raw_ases_json unmodified, plus an
 * empty analysis_options object (no option fields are documented anywhere
 * yet — see §1). Never invents fields the frozen ML Contract doesn't name.
 */
class MLClient
{
    /**
     * @return array<string, mixed> the ML Engine's decoded JSON response body,
     *                              unvalidated — MLResponseValidator checks it next
     *
     * @throws MLCommunicationException
     * @throws MLConfigurationException
     */
    public function analyze(Observation $observation, string $requestId): array
    {
        $token = config('services.ml_engine.token');

        // Credential-enforced, not silently-omitted: a deployment that
        // forgot to configure ML_SERVICE_TOKEN fails loudly here, at first
        // use, rather than sending an unauthenticated request the ML
        // Service (post SECURITY-004) will simply reject with a 401 that's
        // harder to trace back to "nobody set an env var." See integration
        // audit SECURITY-004.
        if (! $token) {
            throw new MLConfigurationException('ML_SERVICE_TOKEN is not configured.');
        }

        try {
            $response = Http::baseUrl(config('services.ml_engine.url'))
                ->timeout(10)
                ->connectTimeout(3)
                // Correlates this specific Backend->ML call with both sides'
                // logs for it. Generated per analysis attempt by the caller
                // (AnalyzeObservationAction), not threaded from the original
                // observation-submission HTTP request — analysis runs later,
                // asynchronously, via a queued Job with no HTTP request
                // context of its own. See Phase 1.5 / OBS-001.
                ->withHeader('X-Request-Id', $requestId)
                ->withToken($token)
                ->post('/analyze', [
                    // `id` is spread AFTER raw_ases_json, deliberately — so
                    // it always wins even if a payload ever contained a
                    // stray top-level `id` key. Observation validation is
                    // structural-only (ADR-002) and never forbids extra
                    // properties, so this can't be assumed away.
                    'observation' => [
                        ...$observation->raw_ases_json,
                        'id' => $observation->id,
                    ],
                    // Cast to stdClass, not a bare []: PHP can't distinguish
                    // an empty array from an empty object, and json_encode()
                    // resolves that ambiguity to `[]` (a JSON array) unless
                    // told otherwise. The ML Service's Pydantic model types
                    // this field as a dict and rejects a JSON array with a
                    // 422 -- caught only by this phase's live-wire test
                    // (LiveMlServiceIntegrationTest), never by Http::fake(),
                    // which never round-trips through real JSON encoding.
                    'analysis_options' => (object) [],
                ]);
        } catch (ConnectionException $e) {
            throw new MLCommunicationException('Unable to reach the ML Engine.', previous: $e);
        }

        if ($response->failed()) {
            throw new MLCommunicationException("ML Engine returned an error response: HTTP {$response->status()}.");
        }

        return $response->json() ?? [];
    }
}
