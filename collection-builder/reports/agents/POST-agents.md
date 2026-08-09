# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/agents`
- Endpoint Name: Create Agent
- Purpose: Creates a new Agent within the caller's Organization. Owner role only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Agent\API\Controllers\AgentController`
- Controller Method: `store`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Agent\API\Middleware\EnsureOwnerRole` (route-group specific)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` — enforced by `EnsureOwnerRole` middleware, which checks `$request->user('api')->role?->value === 'OWNER'`.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

None.

## Request Body

- `name`
- `framework`
- `framework_version` (optional)
- `description` (optional)

## Validation Rules

From `StoreAgentRequest::rules()`:

- `name`: `required`, `string`, `min:1`, `max:255`
- `framework`: `required`, `string`, `min:1`, `max:100`
- `framework_version`: `nullable`, `string`, `max:50`
- `description`: `nullable`, `string`, `max:2000`
- `organization_id`: `prohibited` — never accepted from the request body, rejected outright (422) rather than silently stripped.
- `status`: `prohibited` — same treatment; status cannot be set at creation.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `CreateAgentAction::handle($organizationId, $data, $actorUserId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`AgentController::organizationId()`), never accepted from the request.
  - `AgentPolicy::ensureNameIsAvailable()` checks `AgentRepository::nameExists($organizationId, $data['name'])`; throws `AgentNameConflictException` if the name is already taken — uniqueness is scoped to `(organization_id, name)`, never global.
  - On success, `AgentRepository::create()` persists the Agent with `status` defaulting to `AgentStatus::Active` (model-level default attribute).
  - Dispatches `AgentCreated::dispatch($agent->id, $organizationId, $actorUserId, $agent->name)`.
- Database Operations: `Agent::query()->where('organization_id', ...)->where('name', ...)->exists()` (uniqueness check), then `Agent::create([...$attributes, 'organization_id' => $organizationId])`.
- Events: `AgentCreated` (`agentId`, `organizationId`, `actorUserId`, `agentName`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

201

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
|403|Caller's role is not `OWNER` — rendered by `AgentForbiddenException::render()`: `error.code = FORBIDDEN`, `error.message = "You do not have permission to perform this action."`, `error.details = []`.|
|409|`name` already exists within the caller's Organization — rendered by `AgentNameConflictException::render()`: `error.code = CONFLICT`, `error.message = "An Agent with this name already exists in your organization."`, `error.details = []`.|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by a missing `name`/`framework`, or a `organization_id`/`status` field present in the body (both `prohibited`).|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Inserts one `agents` row.
- Audit Logs: `App\Modules\Audit\Listeners\RecordAgentCreated` listens for `AgentCreated` and records an audit event via `RecordAuditEventAction` with `action: 'agent.created'`, `actorType: ActorType::User`, `resourceType: 'Agent'`, `metadata: ['agent_name' => ...]`.
- Security Logs: Not found in the implementation.
- Events: `AgentCreated` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Agent\Infrastructure\Persistence\Agent`
- Resources:
  - `App\Modules\Agent\Presentation\AgentResource`
- Services: Not found in the implementation. (Uses Action class: `CreateAgentAction`, and `AgentRepository` for persistence access.)
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

- `data.id` (Agent ID)

## Pre-request Requirements

A valid `access_token` for an authenticated Owner-role User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Agent/CreateAgentTest.php`:

- Owner can create an agent.
- Creating an agent with a duplicate name within the same organization returns 409.
- Creating an agent with the same name in a different organization succeeds.
- Creating an agent without a name or framework returns 422.
- `organization_id` in the request body is rejected, never applied (422).
- A new agent is always created with status `ACTIVE`; `status` cannot be set at creation (422 if attempted).
- Member cannot create an agent (403).
- Unauthenticated requests to create an agent are rejected (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents`
- `GET /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}/archive`
