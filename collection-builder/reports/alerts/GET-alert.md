# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/alerts/{alertId}`
- Endpoint Name: Show Alert
- Purpose: Returns full detail for a single Alert belonging to the caller's own Organization, composed with its related Prediction and a minimal Observation embed.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Alert\API\Controllers\AlertController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own doc-block, "All four routes are JWT-only, Owner/Admin/Member equally ... No Role middleware anywhere in this controller."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

- `alertId` — the Alert's ID.

## Query Parameters

None.

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: `App\Modules\Alert\Application\AlertDetail` — per its own doc-block, "a plain read-only carrier, not a new entity: Alert still owns none of Prediction's or Observation's data, it just bundles the three already-fetched objects for the Presentation layer." A `final readonly class` holding `alert`, `prediction`, `observation`.
- Action: `GetAlertAction::handle($organizationId, $alertId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT, never accepted from the request.
  - `AlertRepository::findById($alertId, $organizationId)` scopes the lookup (via the join-through-Prediction/Observation scoping described in the List Alerts report); throws `AlertNotFoundException` if not found (same indistinguishable-by-design 404 as the list endpoint's data-isolation guarantee).
  - Composes the Alert with its related Prediction (`PredictionLookupContract::findById($alert->prediction_id)`) and Observation (`ObservationLookupContract::findByIdForOrganization($prediction->observation_id, $organizationId)`) — per the Action's own doc-block, "a normal, permitted read since Alert -> Analysis -> Observation is entirely downward through the dependency chain."
  - If the referenced Prediction no longer exists, throws a plain `RuntimeException` ("Alert {id} references Prediction {id}, which no longer exists.") — not a typed Domain exception.
  - If the referenced Observation no longer exists, throws the same kind of plain `RuntimeException` for the Observation.
- Database Operations: `Alert::query()->whereHas('prediction.observation', ...)->where('id', ...)->first()` (`AlertRepository::findById()`); a `Prediction` lookup by ID (via `PredictionLookupContract`); an `Observation` lookup scoped by organization (via `ObservationLookupContract`).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Alert\Presentation\AlertDetailResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "severity": ...,
    "status": ...,
    "acknowledged_at": ...,
    "acknowledged_by": ...,
    "resolved_at": ...,
    "resolved_by": ...,
    "created_at": ...,
    "updated_at": ...,
    "prediction": {
      "id": ...,
      "verdict": ...,
      "confidence": ...,
      "risk_score": ...,
      "summary": ...,
      "model_version": ...,
      "analyzed_at": ...,
      "reasons": [...],
      "evidence": [...]
    },
    "observation": {
      "id": ...,
      "agent_id": ...,
      "received_at": ...
    }
  }
}
```

Per `AlertDetailResource`'s own doc-block: the Prediction embed uses `PredictionDetailResource`, not `PredictionSummaryResource` — "this is the one confirmed place the Frontend needs the fuller `evidence` field ... in addition to the `reasons` field every Prediction embed now carries." The `observation` embed is deliberately minimal (`id`, `agent_id`, `received_at` only — no `raw_ases_json`).

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|404|No Alert exists with the given `alertId` in the caller's Organization (including an Alert that exists but belongs to a different Organization) — rendered by `AlertNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Alert not found."`|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

Not found in the implementation: an explicit HTTP response for the plain `RuntimeException` cases (dangling Prediction/Observation reference) — this endpoint's `render()`-based exceptions cover only `AlertNotFoundException`; the two `RuntimeException` throws in `GetAlertAction` are not typed Domain exceptions with their own `render()` method, so they would fall through to the application's generic uncaught-exception handling.

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
  - `App\Modules\Analysis\Infrastructure\Persistence\Prediction` (read via `PredictionLookupContract`, not queried directly by this module)
  - `App\Modules\Observation\Infrastructure\Persistence\Observation` (read via `ObservationLookupContract`, not queried directly by this module)
- Resources:
  - `App\Modules\Alert\Presentation\AlertDetailResource`
  - `App\Modules\Analysis\Presentation\PredictionDetailResource` (embedded)
- Services: Not found in the implementation. (Uses Action class: `GetAlertAction`, and `AlertRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Alert\Domain\AlertStatus` (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`)
  - `App\Modules\Alert\Domain\Severity` (`LOW`, `MEDIUM`, `HIGH`, `CRITICAL`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).
- Other:
  - `App\Modules\Analysis\Application\Contracts\PredictionLookupContract`
  - `App\Modules\Observation\Application\Contracts\ObservationLookupContract`

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User, and an existing `alertId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Alert/ShowAlertTest.php`:

- Owner, Admin, and Member can each view a single alert with embedded prediction and observation.
- An organization cannot view another organization's alert (404).
- A non-existent alert ID returns 404.
- An unauthenticated request cannot view an alert (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/alerts`
- `PATCH /v1/alerts/{alertId}/acknowledge`
- `PATCH /v1/alerts/{alertId}/resolve`
