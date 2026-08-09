# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/agents/{agentId}`
- Endpoint Name: Show Agent
- Purpose: Returns a single Agent belonging to the caller's own Organization.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Agent\API\Controllers\AgentController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per `ShowAgentTest.php`, both Owner and Member roles can view a single agent.

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
- Action: `GetAgentAction::handle($organizationId, $agentId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`AgentController::organizationId()`), never accepted from the request.
  - `AgentRepository::findById($agentId, $organizationId)` scopes the lookup to both `id` and `organization_id`.
  - If no matching Agent is found, throws `AgentNotFoundException`. Per the exception's own doc-block: "Thrown for both a non-existent Agent ID and an Agent belonging to a different Organization — the two cases are indistinguishable by design (Security Through Obscurity)."
- Database Operations: `Agent::query()->where('organization_id', ...)->where('id', ...)->first()` (`AgentRepository::findById()`).
- Events: Not found in the implementation.
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
|404|No Agent exists with the given `agentId` in the caller's Organization (including an Agent that exists but belongs to a different Organization) — rendered by `AgentNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Agent not found."`, `error.details = []`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Not found in the implementation.
- Audit Logs: Not found in the implementation.
- Security Logs: Not found in the implementation.
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Agent\Infrastructure\Persistence\Agent`
- Resources:
  - `App\Modules\Agent\Presentation\AgentResource`
- Services: Not found in the implementation. (Uses Action class: `GetAgentAction`, and `AgentRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User, and an existing `agentId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Agent/ShowAgentTest.php`:

- Owner can view a single agent.
- Member can view a single agent.
- Viewing a non-existent agent returns 404.
- Unauthenticated requests to view an agent are rejected (401).
- Organization A cannot view an agent belonging to Organization B (404, not 403 — data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents`
- `POST /v1/agents`
- `PATCH /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}/archive`
- `GET /v1/agents/{agentId}/observations`
