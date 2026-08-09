"""Centralized constants for the ASES SDK.

Every literal that more than one module needs to agree on lives here, so
values like the retry count or the observation timeout are single-sourced
instead of duplicated across Transport and Observation. This is what keeps
"3 retries, then drop" (10-transport-layer.md) and "no new Event for 30
seconds" (09-observation-lifecycle.md) auditable in one place.
"""

from __future__ import annotations

SDK_NAME = "ases"
SDK_VERSION = "1.0.0"

# --- Networking -------------------------------------------------------
#
# !! ASSUMPTION — CONFIRM AGAINST THE REAL LARAVEL BACKEND !!
#
# 01-overview.md's illustrative example uses `POST /observations`.
# environment-configuration.md documents ASES_ENDPOINT as a *base* URL with
# no path. Neither document states the full path, the API version prefix,
# or the exact Authorization scheme the real Laravel routes/api.php expects.
# The values below are a reasonable, clearly-labeled placeholder — change
# them here (and nowhere else; see transport/client.py) once the real
# contract is confirmed.
DEFAULT_ASES_ENDPOINT = "https://api.sentinelx.example.com"
OBSERVATIONS_PATH = "/api/v1/observations"

DEFAULT_ENVIRONMENT = "production"

# --- Timeouts (RC-9, RELIABILITY-001/002) -------------------------------
#
# Four distinct timeout-shaped concepts exist in this SDK; each is a
# separate constant with a separate meaning, named here together so a
# future reader doesn't have to reconstruct which is which the way the
# ASES audit series had to:
#
#   HTTP_REQUEST_TIMEOUT_SECONDS         — how long a single send attempt
#                                           (one HTTP request to the
#                                           Backend) is allowed to run
#                                           before counting as a failure
#                                           requiring a retry. Mirrors the
#                                           Backend's own outbound budget
#                                           for its Backend->ML call
#                                           (MLClient.php: timeout(10)),
#                                           for consistency across this
#                                           project's various outbound
#                                           HTTP calls — an adjustable
#                                           engineering default, not a
#                                           frozen requirement.
#   TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS (below) — how long the
#                                           shutdown Flush is allowed to
#                                           run, total, across every
#                                           still-queued Observation.
#   OBSERVATION_TIMEOUT_SECONDS (below)  — how long the Collector waits
#                                           for a new Event before
#                                           force-closing an in-flight
#                                           Observation (a Lifecycle
#                                           concept, unrelated to network
#                                           I/O at all).
#   Retry count (TRANSPORT_MAX_RETRIES, below) — not a timeout, but the
#                                           fourth policy this audit found
#                                           readers conflating with the
#                                           three above.
HTTP_REQUEST_TIMEOUT_SECONDS = 10.0

# --- Authentication (RC-8, IDENTITY-001) -------------------------------
#
# Confirmed directly against the real Laravel backend's Agent guard
# (backend/app/Modules/Authentication/AuthServiceProvider.php:34-39):
# Auth::viaRequest('agent-api-key', ...->handle($request->header('X-API-Key'))).
# A single, custom header — never Authorization: Bearer, which is reserved
# for the separate, JWT-based Human guard.
API_KEY_HEADER = "X-API-Key"

# --- Transport failure classification (RC-8, IDENTITY-002) -------------
#
# 401/403 are authentication rejections, not transient failures: retrying
# with the same credential cannot succeed, so these fail fast without
# consuming any of the retry budget below, and are logged distinctly.
AUTH_FAILURE_STATUS_CODES = frozenset({401, 403})

# 429 is retry-worthy, but not identically to a transient network failure —
# the Backend's rate limiter's own Retry-After header (if present) is
# honored instead of the fixed exponential backoff below.
RATE_LIMIT_STATUS_CODE = 429

# --- Transport retry policy (ADR-004, 10-transport-layer.md) ----------
TRANSPORT_MAX_RETRIES = 3
TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS = 5.0

# --- Observation Lifecycle (09-observation-lifecycle.md) --------------
OBSERVATION_TIMEOUT_SECONDS = 30

# --- Adapter signal contract (adapter-signal-contract.md) -------------
SIGNAL_TYPE_EVENT = "EVENT"
SIGNAL_TYPE_OBSERVATION_COMPLETED = "OBSERVATION_COMPLETED"

COMPLETION_REASON_FRAMEWORK_TASK_FINISHED = "framework_task_finished"
COMPLETION_REASON_AGENT_EXECUTION_ENDED = "agent_execution_ended"
COMPLETION_REASON_TIMEOUT = "timeout"
COMPLETION_REASON_SDK_SHUTDOWN = "sdk_shutdown"

VALID_COMPLETION_REASONS = frozenset(
    {
        COMPLETION_REASON_FRAMEWORK_TASK_FINISHED,
        COMPLETION_REASON_AGENT_EXECUTION_ENDED,
        COMPLETION_REASON_TIMEOUT,
        COMPLETION_REASON_SDK_SHUTDOWN,
    }
)

# --- ASES JSON Schema (docs/03-specifications/03-ASES_JSON_SCHEMA.md,
# frontmatter "version: 1.0") -------------------------------------------
SPEC_VERSION = "1.0"

# --- Event Dictionary (docs/03-specifications/02-EVENT_DICTIONARY.md) --
#
# The closed, ten-value canonical event_type vocabulary. Every EventSignal
# is validated against this set at construction (pipeline/events.py) — the
# single choke point every Adapter's output passes through — so that no
# non-canonical value can ever reach the final Observation JSON, regardless
# of which Adapter (shipped or third-party) produced it. Each Adapter is
# responsible for mapping its own framework-specific vocabulary into one of
# these ten values before constructing a signal; this set is what enforces
# that the mapping was actually done.
CANONICAL_EVENT_TYPES = frozenset(
    {
        "api_call",
        "file_access",
        "command_execution",
        "network_connection",
        "database_operation",
        "tool_execution",
        "memory_operation",
        "authentication",
        "configuration_change",
        "custom",
    }
)
