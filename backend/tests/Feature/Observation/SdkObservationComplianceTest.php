<?php

use Illuminate\Support\Facades\Process;

// RC-7 / Phase 7 live verification (Resolution & Integration Plan Principle
// 5): confirms the real, installed ASES SDK's real output — built by
// sdk/ases/'s actual Collector -> Builder -> Validator -> Serializer, not a
// hand-written fixture resembling it — is accepted by this Backend's real,
// current ObservationValidator, end to end, through the real HTTP
// routing/auth/controller stack. This is the first point in this
// integration's history this exact transition (SDK output -> Backend
// acceptance) has been verified live rather than by static analysis.
//
// Requires a `python` interpreter with the sdk/ package installed
// (`pip install -e ./sdk`) to be reachable on PATH. If it isn't, this test
// is skipped rather than failed — this phase's own static/documentation
// fixes are still fully covered by ObservationValidator.php's own existing
// SubmitObservationTest suite regardless.

test('a real Observation built by the live, installed ASES SDK is accepted (202) by the real ObservationValidator', function () {
    $scriptPath = __DIR__.'/fixtures/build_sdk_observation.py';

    $result = Process::timeout(30)->run(['python', $scriptPath]);

    if (! $result->successful()) {
        $this->markTestSkipped(
            'python (with the ases SDK installed via `pip install -e ./sdk`) is not available in this '
            .'environment — see errorOutput: '.$result->errorOutput()
        );
    }

    $sdkObservationJson = trim($result->output());
    $payload = json_decode($sdkObservationJson, associative: true, flags: JSON_THROW_ON_ERROR);

    $agent = createAgentWithKey('ases_live_verification_key');

    $response = $this->withHeader('X-API-Key', 'ases_live_verification_key')
        ->postJson('/api/v1/observations', $payload);

    $response->assertStatus(202)
        ->assertJsonPath('data.analysis_status', 'PENDING');

    $observation = \App\Modules\Observation\Infrastructure\Persistence\Observation::where('agent_id', $agent->id)->firstOrFail();

    expect($observation->raw_ases_json)->toEqual($payload);
});
