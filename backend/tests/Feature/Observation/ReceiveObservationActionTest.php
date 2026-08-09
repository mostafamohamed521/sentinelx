<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Observation\Application\ReceiveObservationAction;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Support\Str;

// Data Isolation: an Agent's API Key can only ever produce Observations
// with that Agent's own agent_id — structurally impossible to prove
// otherwise via the API (no field accepts a different agent_id), so
// verified here at the Application layer instead. See
// 08-implementation-roadmap.md §4.
test('the observation is always attributed to the given agentId and organizationId, never to values inside the payload', function () {
    $agent = Agent::factory()->create();
    $impostorAgentId = (string) Str::uuid();
    $impostorOrganizationId = (string) Str::uuid();

    $payload = validAsesPayload();
    $payload['agent_id'] = $impostorAgentId;
    $payload['organization_id'] = $impostorOrganizationId;

    $observation = app(ReceiveObservationAction::class)->handle(
        agentId: $agent->id,
        organizationId: $agent->organization_id,
        rawBody: json_encode($payload),
    );

    $stored = Observation::findOrFail($observation->id);

    expect($stored->agent_id)->toBe($agent->id)
        ->and($stored->organization_id)->toBe($agent->organization_id)
        ->and($stored->agent_id)->not->toBe($impostorAgentId)
        ->and($stored->organization_id)->not->toBe($impostorOrganizationId);
});
