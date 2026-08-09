# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/agents/{agentId}/observations`
- Endpoint Name: List Agent Observations
- Purpose: Returns a paginated list of Observations for a single Agent, scoped to the caller's own Organization.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Observation\API\Controllers\AgentObservationController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own comment, "JWT (Human) guard, any Role."
- Ownership Note: per `routes/api.php`'s own comment, this route is "Owned by the Observation module (confirmed in `docs/backend/agent/06-api-contract.md §7` as Observation-owned, despite sharing the `/agents` URL prefix — route grouping ≠ module ownership)."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

- `agentId` — the Agent's ID.

## Query Parameters

- `page` — optional; defaults to `1` (`$request->integer('page', 1)`).
- `per_page` — optional; defaults to `20`, clamped to a maximum of `100` (`Controller::perPage()`, `MAX_PER_PAGE = 100`).

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ListAgentObservationsAction::handle($organizationId, $agentId, $perPage, $page)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT, never accepted from the request.
  - Validates the Agent exists and belongs to the caller's Organization via `AgentLookupContract::findActiveAgentForOrganization($agentId, $organizationId)` before querying Observations — per the Action's own doc-block, "never a raw, unchecked `WHERE agent_id = ?`." (Despite its name, this contract method's concrete implementation in `AgentRepository` — `findActiveAgentForOrganization()` — delegates directly to `findById()` with no additional status filter, so an archived Agent's Observations are still listable through this endpoint.)
  - If the Agent is not found (or belongs to a different Organization), throws `AgentNotFoundException`.
  - Otherwise, delegates to `ObservationRepository::listForOrganization($organizationId, $agentId, status: null, $perPage, $page)` — the same repository method used by `GET /v1/observations`, called here with no `analysis_status` filter.
- Database Operations: `Agent::query()->where('organization_id', ...)->where('id', ...)->first()` (Agent existence check, via `AgentRepository::findById()`); `Observation::query()->where('organization_id', ...)->where('agent_id', ...)->orderByDesc('received_at')->paginate(...)`.
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Observation\Presentation\ObservationCollection` (wraps `ObservationSummaryResource`)

## Response Structure

```
{
  "data": [
    {
      "id": ...,
      "agent_id": ...,
      "analysis_status": ...,
      "received_at": ...,
      "created_at": ...
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

Per `ObservationSummaryResource`'s own doc-block, used for both `GET /observations` and `GET /agents/{agentId}/observations`: "Deliberately excludes `raw_ases_json` ... and `organization_id`."

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|404|No Agent exists with the given `agentId` in the caller's Organization (including an Agent that exists but belongs to a different Organization) — rendered by `AgentNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Agent not found."`|
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
  - `App\Modules\Observation\Infrastructure\Persistence\Observation`
  - `App\Modules\Agent\Infrastructure\Persistence\Agent` (read via `AgentLookupContract`, not queried directly by this module)
- Resources:
  - `App\Modules\Observation\Presentation\ObservationCollection`
  - `App\Modules\Observation\Presentation\ObservationSummaryResource`
- Services: Not found in the implementation. (Uses Action class: `ListAgentObservationsAction`, and `ObservationRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Observation\Domain\AnalysisStatus` (referenced by the shared `ObservationSummaryResource`/`listForOrganization`, though this endpoint never filters by it)
- Traits: Not found in the implementation (specific to this endpoint's own logic).
- Other: `App\Modules\Agent\Application\Contracts\AgentLookupContract` — the read-only cross-module interface used to validate the Agent's existence/ownership.

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

Existing coverage found in `backend/tests/Feature/Observation/AgentObservationsTest.php`:

- A human can list observations for a specific agent.
- Requesting observations for another organization's agent returns 404.
- A non-existent agent ID returns 404.
- An unauthenticated request cannot list an agent's observations (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/agents/{agentId}`
- `GET /v1/observations`
- `GET /v1/observations/{observationId}`
