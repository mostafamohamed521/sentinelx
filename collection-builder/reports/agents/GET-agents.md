# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/agents`
- Endpoint Name: List Agents
- Purpose: Returns a paginated list of Agents belonging to the caller's own Organization, optionally filtered by status.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Agent\API\Controllers\AgentController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per `ListAgentsTest.php`, both Owner and Member roles can list agents.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

- `status` — optional; matched case-insensitively against `AgentStatus` via `AgentStatus::tryFrom(strtoupper($status))` (`ACTIVE` or `ARCHIVED`).
- `page` — optional; defaults to `1` (`$request->integer('page', 1)`).
- `per_page` — optional; defaults to `20`, clamped to a maximum of `100` (`Controller::perPage()`, `MAX_PER_PAGE = 100`).

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint; query parameters are read and coerced directly in the controller.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ListAgentsAction::handle(organizationId, status, perPage, page)`, which delegates to `AgentRepository::paginate()`.
- Business Rules:
  - `organization_id` is never accepted from a request parameter — always derived from the authenticated Human's JWT (`$request->user('api')->organization_id`), per the controller's own doc-block.
  - Results are scoped with `where('organization_id', $organizationId)`.
  - If `status` is provided, results are additionally filtered with `where('status', $status)`.
  - Results are ordered by `created_at` ascending.
- Database Operations: `Agent::query()->where('organization_id', ...)->when($status, ...)->orderBy('created_at')->paginate(...)` (`AgentRepository::paginate()`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Agent\Presentation\AgentCollection` (wraps `AgentResource`)

## Response Structure

```
{
  "data": [
    {
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
  ],
  "pagination": {
    "page": ...,
    "per_page": ...,
    "total_items": ...,
    "total_pages": ...
  }
}
```

Per `AgentResource`'s own doc-block: never includes any API Key field, and never includes `organization_id` (every response is already implicitly scoped to the caller's Organization).

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`, `error.message = "Authentication failed."`, `error.details = []`.|
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
  - `App\Modules\Agent\Presentation\AgentCollection`
  - `App\Modules\Agent\Presentation\AgentResource`
- Services: Not found in the implementation. (Uses Action class: `ListAgentsAction`, and `AgentRepository` for persistence access.)
- Policies: Not found in the implementation for this endpoint (`AgentPolicyTest.php` exists in the test suite, but no `AgentPolicy` was invoked in `AgentController::index` or `ListAgentsAction`).
- Enums:
  - `App\Modules\Agent\Domain\AgentStatus` (`ACTIVE`, `ARCHIVED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Agent/ListAgentsTest.php`:

- Owner can list agents belonging to their organization.
- Member can list agents.
- The `status` query parameter filters the list.
- Unauthenticated requests to list agents are rejected (401).
- Organization A never sees Organization B agents in the list (data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/agents`
- `GET /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}`
- `PATCH /v1/agents/{agentId}/archive`
- `GET /v1/agents/{agentId}/observations`
