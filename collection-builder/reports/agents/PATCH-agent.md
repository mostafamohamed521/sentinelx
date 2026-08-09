# Endpoint Inspection Report

---

# Endpoint

- Method: PATCH
- Route: `/v1/agents/{agentId}`
- Endpoint Name: Update Agent
- Purpose: Updates an existing Agent's metadata (name, framework, framework_version, description) within the caller's Organization. Owner role only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Agent\API\Controllers\AgentController`
- Controller Method: `update`
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

- `name` (optional)
- `framework` (optional)
- `framework_version` (optional)
- `description` (optional)

## Validation Rules

From `UpdateAgentRequest::rules()`:

- `name`: `sometimes`, `string`, `min:1`, `max:255`
- `framework`: `sometimes`, `string`, `min:1`, `max:100`
- `framework_version`: `sometimes`, `nullable`, `string`, `max:50`
- `description`: `sometimes`, `nullable`, `string`, `max:2000`
- `organization_id`: `prohibited` — never client-supplied.
- `status`: `prohibited` — immutable via this endpoint; only changes via Archive.

Additional rule via `withValidator()`: at least one of `name`, `framework`, `framework_version`, `description` must be present in the request body, otherwise an error is added under the `_` key ("At least one field must be provided.").

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `UpdateAgentAction::handle($organizationId, $agentId, $data, $actorUserId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`AgentController::organizationId()`), never accepted from the request.
  - `AgentRepository::findById($agentId, $organizationId)` scopes the lookup to both `id` and `organization_id`; throws `AgentNotFoundException` if not found (same indistinguishable-by-design 404 as `show`/`archive`).
  - If `name` is present in `$data`, `AgentPolicy::ensureNameIsAvailable()` checks `AgentRepository::nameExists($organizationId, $data['name'], excludeAgentId: $agent->id)` — the Agent's own current name is excluded from the collision check; throws `AgentNameConflictException` if another Agent already has that name.
  - `AgentRepository::update()` persists only the supplied fields and refreshes the model.
  - Dispatches `AgentUpdated::dispatch($agent->id, $organizationId, $actorUserId)`.
- Database Operations: `Agent::query()->where('organization_id', ...)->where('id', ...)->first()` (lookup); `Agent::query()->where('organization_id', ...)->where('name', ...)->where('id', '!=', $excludeAgentId)->exists()` (name-collision check, when `name` is present); `$agent->update($attributes)` then `$agent->refresh()`.
- Events: `AgentUpdated` (`agentId`, `organizationId`, `actorUserId`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Agent\Presentation\AgentResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "name": ...,
    "framework": ...,
    "framework_version": ...,
    "description": ...,
    "status": ...,
    "last_seen_at": ...,
    "created_at": ...,
    "updated_at": ...
  }
}
```

Per `AgentResource`'s own doc-block: never includes any API Key field, and never includes `organization_id`.

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is not `OWNER` — rendered by `AgentForbiddenException::render()`: `error.code = FORBIDDEN`.|
|404|No Agent exists with the given `agentId` in the caller's Organization (including an Agent that exists but belongs to a different Organization) — rendered by `AgentNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Agent not found."`|
|409|`name` already exists on another Agent within the caller's Organization — rendered by `AgentNameConflictException::render()`: `error.code = CONFLICT`, `error.message = "An Agent with this name already exists in your organization."`|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by an empty body (no mutable field supplied), an `organization_id`/`status` field present (both `prohibited`), or an invalid `name`/`framework`/`framework_version`/`description`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Updates the supplied fields on the `agents` row.
- Audit Logs: `App\Modules\Audit\Listeners\RecordAgentUpdated` listens for `AgentUpdated` and records an audit event via `RecordAuditEventAction` with `action: 'agent.updated'`, `actorType: ActorType::User`, `resourceType: 'Agent'`.
- Security Logs: Not found in the implementation.
- Events: `AgentUpdated` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Agent\Infrastructure\Persistence\Agent`
- Resources:
  - `App\Modules\Agent\Presentation\AgentResource`
- Services: Not found in the implementation. (Uses Action class: `UpdateAgentAction`, and `AgentRepository` for persistence access.)
- Policies:
  - `App\Modules\Agent\Domain\AgentPolicy` (`ensureNameIsAvailable()`)
- Enums:
  - `App\Modules\Agent\Domain\AgentStatus` (`ACTIVE`, `ARCHIVED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated Owner-role User, and an existing `agentId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Agent/UpdateAgentTest.php`:

- Owner can update an agent's metadata.
- Renaming an agent to a name already used by another agent in the same organization returns 409.
- An empty `PATCH` body returns 422.
- Updating a non-existent agent returns 404.
- `status` cannot be set directly via `PATCH` (422; value unchanged).
- `organization_id` cannot be set via `PATCH` (422).
- Member cannot update an agent (403).
- Organization A cannot update an agent belonging to Organization B (404, not 403 — data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents/{agentId}`
- `POST /v1/agents`
- `PATCH /v1/agents/{agentId}/archive`
