# Endpoint Inspection Report

---

# Endpoint

- Method: PATCH
- Route: `/v1/agents/{agentId}/archive`
- Endpoint Name: Archive Agent
- Purpose: Archives an Agent within the caller's Organization, revoking its active API Key(s) as a side effect. Owner role only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Agent\API\Controllers\AgentController`
- Controller Method: `archive`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Agent\API\Middleware\EnsureOwnerRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` — enforced by `EnsureOwnerRole` middleware, which checks `$request->user('api')->role?->value === 'OWNER'`.

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
- Action: `ArchiveAgentAction::handle($organizationId, $agentId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`AgentController::organizationId()`), never accepted from the request.
  - `AgentRepository::findById($agentId, $organizationId)` scopes the lookup to both `id` and `organization_id`; throws `AgentNotFoundException` if not found (same indistinguishable-by-design 404 as `show`/`update`).
  - `AgentPolicy::ensureCanBeArchived($agent->status)` throws `AgentAlreadyArchivedException` if the Agent's status is already `ARCHIVED` — per the exception's own doc-block, "Archiving an already-ARCHIVED Agent is a 409, never a silent 200 ... Silently succeeding would hide a caller's stale assumption about the Agent's state from them."
  - `AgentRepository::archive()` sets `status = AgentStatus::Archived` and refreshes the model.
  - Dispatches `AgentArchived::dispatch($agent->id, $organizationId)`.
- Database Operations: `Agent::query()->where('organization_id', ...)->where('id', ...)->first()` (lookup); `$agent->update(['status' => AgentStatus::Archived])` then `$agent->refresh()`.
- Events: `AgentArchived` (`agentId`, `organizationId`) — per the event's own doc-block, "The API Key submodule (Authentication) is the one known listener today."
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` from `AgentController::archive()`, not an Eloquent API Resource.

## Response Structure

```
{
  "data": {
    "id": ...,
    "status": ...,
    "updated_at": ...
  }
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is not `OWNER` — rendered by `AgentForbiddenException::render()`: `error.code = FORBIDDEN`.|
|404|No Agent exists with the given `agentId` in the caller's Organization (including an Agent that exists but belongs to a different Organization) — rendered by `AgentNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Agent not found."`|
|409|The Agent is already archived — rendered by `AgentAlreadyArchivedException::render()`: `error.code = CONFLICT`, `error.message = "This Agent is already archived."`|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes:
  - Sets the Agent's `status` to `ARCHIVED`.
  - `App\Modules\Authentication\ApiKey\Listeners\RevokeKeysOnAgentArchived` listens for `AgentArchived` and updates every `ApiKey` row where `agent_id` matches and `status = Active` to `status = Revoked` — a cross-module reaction the Agent module has no knowledge of.
- Audit Logs: `App\Modules\Audit\Listeners\RecordAgentArchived` listens for `AgentArchived` and records an audit event via `RecordAuditEventAction` with `action: 'agent.archived'`, `actorType: ActorType::User`, `resourceType: 'Agent'`. Per this listener's own doc-block, since `AgentArchived` carries no actor field, the acting User is resolved from the current `auth('api')` guard state (synchronous, same-request) rather than from the event payload — the only listener in the Audit module that does this.
- Security Logs: Not found in the implementation.
- Events: `AgentArchived` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Agent\Infrastructure\Persistence\Agent`
  - `App\Modules\Authentication\ApiKey\Infrastructure\Persistence\ApiKey` (updated by the `RevokeKeysOnAgentArchived` listener, not directly by this endpoint's own Action)
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `ArchiveAgentAction`, and `AgentRepository` for persistence access.)
- Policies:
  - `App\Modules\Agent\Domain\AgentPolicy` (`ensureCanBeArchived()`)
- Enums:
  - `App\Modules\Agent\Domain\AgentStatus` (`ACTIVE`, `ARCHIVED`)
  - `App\Modules\Authentication\ApiKey\Domain\ApiKeyStatus` (referenced by the API Key revocation side effect)
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated Owner-role User, and an existing, non-archived `agentId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Agent/ArchiveAgentTest.php` and `backend/tests/Feature/Agent/AgentArchivalRevokesApiKeyTest.php`:

- Owner can archive an agent.
- Archiving an already-archived agent returns 409, not a silent success.
- Archiving a non-existent agent returns 404.
- Member cannot archive an agent (403).
- Unauthenticated requests to archive an agent are rejected (401).
- Organization A cannot archive an agent belonging to Organization B (404, data isolation).
- Archiving an agent revokes its active API key via the `AgentArchived` event.
- A revoked API key can no longer authenticate the agent after archival.

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}`
- `POST /v1/agents/{agentId}/rotate-api-key`
