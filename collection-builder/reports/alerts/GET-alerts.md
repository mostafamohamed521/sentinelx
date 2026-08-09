# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/alerts`
- Endpoint Name: List Alerts
- Purpose: Returns a paginated list of Alerts belonging to the caller's own Organization, optionally filtered by status and severity.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Alert\API\Controllers\AlertController`
- Controller Method: `index`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own doc-block, "All four routes are JWT-only, Owner/Admin/Member equally ... No Role middleware anywhere in this controller: 'is an authenticated Human' is the entire check."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

- `status` — optional; matched case-insensitively against `AlertStatus` via `AlertStatus::tryFrom(strtoupper($status))` (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`).
- `severity` — optional; matched case-insensitively against `Severity` via `Severity::tryFrom(strtoupper($severity))` (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`).
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
- Action: `ListAlertsAction::handle(organizationId, status, severity, perPage, page)`, which delegates to `AlertRepository::listForOrganization()`.
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`AlertController::organizationId()`), never accepted from the request.
  - `alerts` has no `organization_id` column of its own — per `AlertRepository`'s own doc-block, every organization-scoped query joins through `Prediction -> Observation` to reach `observations.organization_id` (`whereHas('prediction.observation', ...)`), the one sanctioned exception to "never query predictions/observations directly," used strictly for scoping.
  - `prediction` is eager-loaded (`with('prediction')`) to avoid an N+1 query, since `AlertSummaryResource` reads `reasons` off the related Prediction.
  - If `status` is provided, results are additionally filtered with `where('status', $status)`.
  - If `severity` is provided, results are additionally filtered with `where('severity', $severity)`.
  - Results are ordered by `created_at` descending.
- Database Operations: `Alert::query()->whereHas('prediction.observation', fn ($q) => $q->where('organization_id', ...))->with('prediction')->when($status, ...)->when($severity, ...)->orderByDesc('created_at')->paginate(...)` (`AlertRepository::listForOrganization()`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Alert\Presentation\AlertCollection` (wraps `AlertSummaryResource`)

## Response Structure

```
{
  "data": [
    {
      "id": ...,
      "prediction_id": ...,
      "severity": ...,
      "status": ...,
      "created_at": ...,
      "reasons": [...]
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

Per `AlertSummaryResource`'s own doc-block: "Deliberately minimal, excluding the related Observation/Prediction detail ... `reasons` is the one exception" — small human-readable justification strings read off the eager-loaded `prediction` relation, included because a frontend Related-Alerts list reads `reasons[0]` directly from this same list endpoint with no detail-view fetch to source it from instead. The fuller, structured `evidence` field is deliberately not included here.

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
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
  - `App\Modules\Alert\Infrastructure\Persistence\Alert`
  - Related `Prediction` / `Observation` (joined only for organization-scoping, per `AlertRepository`'s own doc-block — business data still sourced from their own modules elsewhere, e.g. `GetAlertAction`)
- Resources:
  - `App\Modules\Alert\Presentation\AlertCollection`
  - `App\Modules\Alert\Presentation\AlertSummaryResource`
- Services: Not found in the implementation. (Uses Action class: `ListAlertsAction`, and `AlertRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Alert\Domain\AlertStatus` (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`)
  - `App\Modules\Alert\Domain\Severity` (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`)
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

Existing coverage found in `backend/tests/Feature/Alert/ListAlertsTest.php`:

- Owner, Admin, and Member can each list alerts for their organization.
- Alerts can be filtered by `status` and `severity`.
- An organization never sees another organization's alerts in the list (data isolation).
- An unauthenticated request cannot list alerts (401).
- An Agent (API Key) cannot list alerts (401).
- No `POST` or `DELETE` route exists for alerts (405).
- No `reopen` route exists for alerts (404).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/alerts/{alertId}`
- `PATCH /v1/alerts/{alertId}/acknowledge`
- `PATCH /v1/alerts/{alertId}/resolve`
