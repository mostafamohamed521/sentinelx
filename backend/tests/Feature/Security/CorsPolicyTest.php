<?php

// === SECURITY-001: CORS is an explicit, reviewable decision, not a wildcard default ===

test('a CORS preflight from the trusted Frontend origin is allowed', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:5173',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/agents')
        ->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
});

test('a CORS preflight from an untrusted origin is not granted access', function () {
    $response = $this->withHeaders([
        'Origin' => 'https://attacker.example',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/v1/agents');

    expect($response->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://attacker.example');
});

test('the published CORS policy does not use a wildcard origin', function () {
    expect(config('cors.allowed_origins'))->not->toContain('*');
});
