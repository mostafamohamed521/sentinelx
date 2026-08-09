<?php

// === BUSINESS RULE (cross-module integration: Observation -> Agent's touchLastSeen) ===

test('submitting an observation updates the agents last_seen_at', function () {
    $agent = createAgentWithKey('a-valid-raw-secret', agentState: ['last_seen_at' => null]);

    expect($agent->last_seen_at)->toBeNull();

    $this->withHeader('X-API-Key', 'a-valid-raw-secret')
        ->postJson('/api/v1/observations', validAsesPayload())
        ->assertStatus(202);

    expect($agent->fresh()->last_seen_at)->not->toBeNull();
});

// Deliberately not tested here: "a failed submission never updates
// last_seen_at". Authentication\ApiKey\Application\ValidateApiKeyAction
// (Sprint 2) already touches last_seen_at unconditionally on every
// successful API-key authentication, before this module's own
// ReceiveObservationAction ever runs — independent of whether the
// request that follows succeeds or fails. ReceiveObservationAction's own
// touchLastSeen() call is correctly transactional with the Observation
// insert per 05-cross-module-boundaries.md §1, but it cannot undo a write
// the guard already made. Fixing that would mean modifying Authentication
// module internals, which is out of this Sprint's scope — flagged rather
// than silently reached past.
