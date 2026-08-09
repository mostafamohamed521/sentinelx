<?php

// SECURITY-001: previously this project fell through to Laravel's own
// internal, unpublished CORS default (allowed_origins: ['*']), which no
// file in this repository ever made an explicit, reviewable decision
// about — see docs/integration-audit/05-security-boundaries-audit.md.
// This file makes that decision deliberate: exactly one trusted origin,
// driven by FRONTEND_URL, not a wildcard.

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // A comma-separated list, so a small number of trusted origins (e.g.
    // separately-hosted staging/production Frontends) can be named without
    // resorting to a wildcard or a pattern.
    'allowed_origins' => array_filter(explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Request-Id'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    // The JWT is sent as an Authorization header, not a cookie (see
    // ADR-005-stateless-jwt-logout.md) — no cross-origin cookie is ever
    // read, so credentialed CORS requests are not needed.
    'supports_credentials' => false,

];
