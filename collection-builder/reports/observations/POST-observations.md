# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/observations`
- Endpoint Name: Submit Observation
- Purpose: Accepts an Agent-submitted ASES Observation payload for asynchronous analysis. API Key (Agent) guard only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Observation\API\Controllers\ObservationController`
- Controller Method: `store`
- Middleware: `auth:agent` (enclosing route group), `throttle:observation-ingestion`
- Throttle: `observation-ingestion` rate limiter — 300 requests per minute, keyed by the authenticated Agent's ID (falls back to IP if no Agent is resolved) (`AppServiceProvider::registerRateLimiters`). Per that method's own comment: "the single highest-volume, externally-reachable, adversary-reachable endpoint in the system."
- Authentication Guard: `agent` (`config/auth.php` `guards.agent.driver = 'agent-api-key'`) — a custom `Auth::viaRequest()` guard reading the `X-API-Key` header, resolved by `ValidateApiKeyAction`.
- Required Role: Not applicable — this route can only ever be reached by an Agent (API Key), never a Human/JWT; per the controller's own comment, "API Key (Agent) guard only."

---

# Request

## Headers

- `X-API-Key: {raw_key}` — required by the `agent-api-key` guard.
- `Content-Type: application/json` (implied — the raw request body is decoded as JSON by `ReceiveObservationAction`).

## Path Parameters

None.

## Query Parameters

None.

## Request Body

Structural shape enforced by `ObservationValidator::validate()` (not a Form Request):

- `context` (object, required)
  - `context.framework` (non-empty string, required)
  - `context.execution_start_time` (ISO 8601 timestamp, required)
  - `context.execution_finish_time` (ISO 8601 timestamp, required; must not be before `execution_start_time`)
- `events` (array, required)
  - Must be a list with at least 1 and at most 1000 entries (`ObservationValidator::MAX_EVENTS`).
  - Each event: `header.event_type` (must be one of the ten canonical types: `api_call`, `file_access`, `command_execution`, `network_connection`, `database_operation`, `tool_execution`, `memory_operation`, `authentication`, `configuration_change`, `custom`), `header.timestamp` (ISO 8601, required), `payload` (object, required — content itself is never validated beyond "is an object").
  - Events must be in chronological order by `header.timestamp`.
- `metadata` (object, required)
  - `metadata.spec_version` (non-empty string, required)
  - `metadata.sdk_version` (non-empty string, required)

Per `SubmitObservationTest.php`: any `organization_id`/`agent_id` fields present in the payload body are inert and never used for ownership — those are always derived from the authenticated Agent.

## Validation Rules

`SubmitObservationRequest` deliberately carries no `rules()` — per its own doc-block, "keeps the full definition of 'valid ASES' in exactly one place" (`ObservationValidator`), rather than splitting checks between a FormRequest and the Domain layer. All five structural checks (see `ObservationValidator`, referenced as Checks 1–5 in `04-validation.md §3`) are implemented there:

1. Request body must be valid JSON (`ReceiveObservationAction::decode()`, throws `MalformedObservationPayloadException` — checked before the validator runs).
2–5. `context`, `events`, and `metadata` structural checks as listed above (throws `ObservationValidationFailedException` with exactly one `field`/`reason` pair, fail-fast on the first violation).

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ReceiveObservationAction::handle($agentId, $organizationId, $rawBody)`
- Business Rules:
  - `agentId` and `organizationId` are always derived from `$request->user('agent')`, never from the request body.
  - The raw body is decoded as JSON (`json_decode(..., flags: JSON_THROW_ON_ERROR)`); a decode failure or non-array result throws `MalformedObservationPayloadException`.
  - `ObservationValidator::validate($payload)` runs all five structural checks, fail-fast.
  - Inside `DB::transaction()`: creates the `Observation` row (`analysis_status = Pending`, `received_at = now()`), then calls `AgentRepository::touchLastSeen($agentId, $receivedAt)` in the same transaction — per the Action's own doc-block, "the one legitimate write this module makes outside its own table ... so a rolled-back Observation insert never leaves `last_seen_at` moved."
  - Logs `'Observation received.'` via `Log::info()` with `observation_id` and `agent_id`.
- Database Operations: `Observation::create([...])`; `Agent::query()->where('id', $agentId)->update(['last_seen_at' => $timestamp])` — both inside the same `DB::transaction()`.
- Events: Not found in the implementation — no domain event is dispatched on Observation receipt.
- Jobs: Not found in the implementation (analysis is asynchronous per the endpoint's own 202 contract, but no job dispatch was found in this Action).
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

202

Per the controller's own comment: "202, not 201/200 — a deliberate, already-decided contract choice" (`adr/ADR-001-async-ingestion-202-accepted.md`).

## Response Resource

`App\Modules\Observation\Presentation\ObservationAcceptedResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "received_at": ...,
    "analysis_status": "PENDING"
  }
}
```

Per the Resource's own doc-block: "deliberately minimal. The SDK's only remaining responsibility is knowing the submission succeeded; it must never receive any analysis result synchronously, since none exists yet."

---

# Error Responses

| Status | Condition |
|---------|-----------|
|400|The request body is not valid JSON — rendered by `MalformedObservationPayloadException::render()`: `error.code = BAD_REQUEST`, `error.message = "The request body is not valid JSON."`|
|401|No/invalid/revoked/expired API Key, an Agent that is not `ACTIVE`, or an Organization that is not `ACTIVE` — all resolved to `null` by `ValidateApiKeyAction`, causing the guard to fail. Also returned for a valid JWT with no API Key (wrong guard entirely).|
|422|A structural validation failure in `context`, `events`, or `metadata` — rendered by `ObservationValidationFailedException::render()`: `error.code = VALIDATION_ERROR`, `error.message = "The submitted observation is invalid."`, `error.details = {field, reason}` (exactly one field/reason pair per failure).|
|429|Rate limit exceeded — `throttle:observation-ingestion` (300 requests/minute per Agent). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes:
  - Inserts one `observations` row (`analysis_status = PENDING`).
  - Updates the Agent's `last_seen_at` (via `AgentRepository::touchLastSeen()`, in the same transaction as the Observation insert).
  - At the guard layer (`ValidateApiKeyAction`, runs before the Action, not part of it): updates the matched `ApiKey`'s `last_used_at` and the Agent's `last_seen_at` again, on every successful authentication regardless of what the request does afterward.
- Audit Logs: Not found in the implementation.
- Security Logs: `Log::info('Observation received.', ['observation_id' => ..., 'agent_id' => ...])`.
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Observation\Infrastructure\Persistence\Observation`
  - `App\Modules\Agent\Infrastructure\Persistence\Agent` (updated via `AgentRepository::touchLastSeen()`)
- Resources:
  - `App\Modules\Observation\Presentation\ObservationAcceptedResource`
- Services: Not found in the implementation. (Uses Action class: `ReceiveObservationAction`, plus `ObservationRepository` and `AgentRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Observation\Domain\AnalysisStatus` (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).
- Other: `App\Modules\Observation\Domain\ObservationValidator` — pure PHP domain validator, no Eloquent/HTTP dependency, implementing the five structural checks described above.

---

# Postman Collection Notes

## Authorization

`X-API-Key: {raw_key}` header — a raw API Key obtained from `POST /v1/agents/{agentId}/rotate-api-key`. Not a bearer JWT.

## Variables To Save

- `data.id` (Observation ID)

## Pre-request Requirements

An Agent with an `ACTIVE` status, an `ACTIVE` (non-expired) API Key, and an `ACTIVE` Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Observation/SubmitObservationTest.php`:

- An authenticated agent can submit a well-formed observation (202).
- An observation with zero events is rejected with 422.
- An observation with out-of-order event timestamps is rejected with 422.
- An observation missing a required context field is rejected with 422.
- A malformed, non-JSON request body is rejected with 400.
- An event type outside the canonical event dictionary is rejected with 422.
- `organization_id`/`agent_id` fields inside the payload are inert, never used for ownership.
- `analysis_status` is always exactly `PENDING` immediately after a successful submission.
- There is no `PATCH`, `PUT`, or `DELETE` route for observations.
- A valid JWT with no API Key cannot submit an observation (401).
- An agent whose API Key was just revoked cannot submit an observation (401).
- An archived agent cannot submit an observation (401).
- An unauthenticated request cannot submit an observation (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/observations`
- `GET /v1/observations/{observationId}`
- `POST /v1/agents/{agentId}/rotate-api-key`
