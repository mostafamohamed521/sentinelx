# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/observations`
- Endpoint Name: List Observations
- Purpose: Returns a paginated list of Observations belonging to the caller's own Organization, optionally filtered by Agent and analysis status.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Observation\API\Controllers\ObservationController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own comment, "JWT (Human) guard, any Role," and confirmed by `ListObservationsTest.php` for Owner, Admin, and Member.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

- `agent_id` — optional; filters results to a single Agent (`$request->query('agent_id')`, passed through with no format validation).
- `analysis_status` — optional; matched case-insensitively against `AnalysisStatus` via `AnalysisStatus::tryFrom(strtoupper($status))` (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`).
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
- Action: `ListObservationsAction::handle(organizationId, agentId, status, perPage, page)`, which delegates to `ObservationRepository::listForOrganization()`.
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`ObservationController::organizationId()`), never accepted from the request.
  - Results are scoped with `where('organization_id', $organizationId)`.
  - If `agent_id` is provided, results are additionally filtered with `where('agent_id', $agentId)`.
  - If `analysis_status` is provided, results are additionally filtered with `where('analysis_status', $status)`.
  - Results are ordered by `received_at` descending.
- Database Operations: `Observation::query()->where('organization_id', ...)->when($agentId, ...)->when($status, ...)->orderByDesc('received_at')->paginate(...)` (`ObservationRepository::listForOrganization()`).
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

Per `ObservationSummaryResource`'s own doc-block: "Deliberately excludes `raw_ases_json` (can be large; a list view has no need for it) and `organization_id` (every response is already implicitly scoped to the caller's Organization)."

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or a valid API Key with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
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
- Resources:
  - `App\Modules\Observation\Presentation\ObservationCollection`
  - `App\Modules\Observation\Presentation\ObservationSummaryResource`
- Services: Not found in the implementation. (Uses Action class: `ListObservationsAction`, and `ObservationRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Observation\Domain\AnalysisStatus` (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`)
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

Existing coverage found in `backend/tests/Feature/Observation/ListObservationsTest.php`:

- Owner, Admin, and Member can all list observations for their organization.
- The list response excludes `raw_ases_json`.
- An organization's observation list never includes another organization's observations (data isolation).
- A valid API Key with no JWT cannot list observations (401).
- An unauthenticated request cannot list observations (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/observations/{observationId}`
- `POST /v1/observations`
- `GET /v1/agents/{agentId}/observations`
