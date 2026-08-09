# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/agents/{agentId}/rotate-api-key`
- Endpoint Name: Rotate API Key
- Purpose: Issues a new API Key for an Agent, revoking any prior active key. Owner role only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\ApiKey\API\Controllers\ApiKeyController`
- Controller Method: `rotate`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Agent\API\Middleware\EnsureOwnerRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` — enforced by `EnsureOwnerRole` middleware (reused from the Agent module — per the route's own comment: "Reuses `EnsureOwnerRole` because Authentication is allowed to depend on Agent").

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

- `agentId` — the Agent's ID.

## Query Parameters

None.

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `RotateApiKeyAction::handle($organizationId, $agentId, $actorUserId)`
- Business Rules:
  - Looks up the Agent via `AgentLookupContract::findActiveAgentForOrganization($agentId, $organizationId)` — the API Key submodule never queries the `agents` table directly, only through this read-only contract (module-dependency rule).
  - If no Agent is found, throws `AgentNotFoundException`.
  - If the Agent's `status !== AgentStatus::Active`, throws `AgentNotActiveException`.
  - Checks for a prior `ApiKey` with `status = Active` for the Agent (`$priorActiveKey`) before generating the new key — this determines which event(s) to dispatch, not whether generation succeeds.
  - Delegates key creation to `GenerateApiKeyAction::handle($agent->id)`, which creates a new `ApiKey` (`status = Active`) with a random 32-character secret (`sk_live_{secret}`), storing only `key_prefix` (`sk_live_` + first 4 chars) and `key_hash` (`sha256` of the raw key). The raw key is returned exactly once and never persisted.
  - `ApiKey::booted()`'s `saved` hook enforces "one ACTIVE key per Agent" at the model layer: whenever an `ApiKey` is saved with `status = Active`, every other `Active` key for the same `agent_id` is auto-updated to `Revoked`.
  - If a prior active key existed, dispatches `ApiKeyRotated` (for the new key) and `ApiKeyRevoked` (for the prior key); otherwise dispatches `ApiKeyGenerated` (first-ever key for this Agent).
- Database Operations: `ApiKey::query()->where('agent_id', ...)->where('status', Active)->first()` (prior-key check); `ApiKey::create([...])` (new key); implicit `ApiKey::query()->where('agent_id', ...)->where('id', '!=', ...)->where('status', Active)->update(['status' => Revoked])` via the model's `booted()` hook.
- Events: `ApiKeyGenerated` / `ApiKeyRotated` / `ApiKeyRevoked` (all carry `apiKeyId`, `agentId`, `organizationId`, `actorUserId`), dispatched conditionally as described above.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

201

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` from `ApiKeyController::rotate()`, not an Eloquent API Resource.

## Response Structure

```
{
  "data": {
    "key_prefix": ...,
    "raw_key": ...,
    "status": ...,
    "created_at": ...
  }
}
```

Per `GenerateApiKeyAction`'s own doc-block: "The raw key is returned exactly once and is never persisted or retrievable again."

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is not `OWNER` — rendered by `AgentForbiddenException::render()`: `error.code = FORBIDDEN`.|
|404|No Agent exists with the given `agentId` in the caller's Organization (including an Agent that exists but belongs to a different Organization) — rendered by `AgentNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Agent not found."`|
|409|The Agent's status is not `ACTIVE` (e.g. archived) — rendered by `AgentNotActiveException::render()`: `error.code = CONFLICT`, `error.message = "Cannot issue an API key for an Agent that is not active."`|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes:
  - Inserts one `api_keys` row (`status = Active`).
  - Auto-revokes any other `Active` `ApiKey` row for the same `agent_id` (via `ApiKey::booted()`'s `saved` hook).
- Audit Logs:
  - `App\Modules\Audit\Listeners\RecordApiKeyGenerated` → `action: 'api_key.generated'` (first-ever key), `resourceType: 'ApiKey'`, `metadata: ['agent_id' => ...]`.
  - `App\Modules\Audit\Listeners\RecordApiKeyRotated` → `action: 'api_key.rotated'` (new key on a genuine rotation), same resource/metadata shape.
  - `App\Modules\Audit\Listeners\RecordApiKeyRevoked` → `action: 'api_key.revoked'` (the key being replaced on a genuine rotation), same resource/metadata shape.
- Security Logs: Not found in the implementation.
- Events: `ApiKeyGenerated`, `ApiKeyRotated`, `ApiKeyRevoked` (conditional, see Processing).
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey`
  - `App\Modules\Agent\Infrastructure\Persistence\Agent` (accessed only via `AgentLookupContract`, never queried directly)
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action classes: `RotateApiKeyAction`, `GenerateApiKeyAction`.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus` (referenced: `Active`, `Revoked`)
  - `App\Modules\Agent\Domain\AgentStatus` (`ACTIVE`, `ARCHIVED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER`.

## Variables To Save

- `data.raw_key` (shown only once)
- `data.key_prefix`

## Pre-request Requirements

A valid `access_token` for an authenticated Owner-role User, and an existing, `ACTIVE`-status `agentId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/RotateApiKeyTest.php`:

- Owner can rotate an API key for their agent.
- Rotating a second time revokes the first key and issues a new one.
- The raw key returned by rotation authenticates the agent.
- Rotating a key for an archived agent returns 409.
- Rotating a key for a non-existent agent returns 404.
- Member cannot rotate an API key (403).
- Unauthenticated requests to rotate an API key are rejected (401).
- Organization A cannot rotate an API key for an agent belonging to Organization B (404, data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}/archive`
