# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/observations/{observationId}`
- Endpoint Name: Show Observation
- Purpose: Returns full detail for a single Observation belonging to the caller's own Organization, including its Prediction (if analysis has completed).

---

# Routing

- API Version: v1
- Controller: `App\Modules\Observation\API\Controllers\ObservationController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own comment, "JWT (Human) guard, any Role," and confirmed by `ShowObservationTest.php` for Owner, Admin, and Member.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

- `observationId` — the Observation's ID.

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
- Action: `GetObservationAction::handle($organizationId, $observationId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`ObservationController::organizationId()`), never accepted from the request.
  - `ObservationRepository::findById($observationId, $organizationId)` scopes the lookup to both `id` and `organization_id`.
  - If no matching Observation is found, throws `ObservationNotFoundException`. Per the exception's own doc-block: "Thrown for both a non-existent Observation ID and an Observation belonging to a different Organization — indistinguishable by design (Security Through Obscurity)."
  - After the Observation is resolved, the controller separately composes the `prediction` field: `PredictionLookupContract::findByObservationId($observation->id)` — per that contract's own doc-block, this is "consumed within the same request cycle by Observation's own `ObservationController@show`, to complete the `prediction` field left null since Stage 3." This call is made directly in the controller, not inside `GetObservationAction`.
- Database Operations: `Observation::query()->where('organization_id', ...)->where('id', ...)->first()` (`ObservationRepository::findById()`); a `Prediction` lookup by `observation_id` (via `PredictionLookupContract`, implementation not part of this module).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Observation\Presentation\ObservationResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "agent_id": ...,
    "organization_id": ...,
    "analysis_status": ...,
    "raw_ases_json": ...,
    "received_at": ...,
    "processing_started_at": ...,
    "processed_at": ...,
    "created_at": ...,
    "updated_at": ...,
    "prediction": null | {
      "id": ...,
      "verdict": ...,
      "confidence": ...,
      "risk_score": ...,
      "summary": ...,
      "model_version": ...,
      "analyzed_at": ...,
      "reasons": [...]
    }
  }
}
```

Per `ObservationResource`'s own doc-block: this is the "Full detail view ... including `raw_ases_json` (unlike `ObservationSummaryResource`)." `prediction` "stays null for every `analysis_status` other than `COMPLETED`, including `FAILED`." The embedded `prediction` object is produced by `PredictionSummaryResource`, which — per its own doc-block — "deliberately excludes the fuller `evidence` field and the rest of `prediction_json`."

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|404|No Observation exists with the given `observationId` in the caller's Organization (including an Observation that exists but belongs to a different Organization) — rendered by `ObservationNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Observation not found."`|
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
  - `App\Modules\Analysis\Infrastructure\Persistence\Prediction` (read via `PredictionLookupContract`, not queried directly by this module)
- Resources:
  - `App\Modules\Observation\Presentation\ObservationResource`
  - `App\Modules\Analysis\Presentation\PredictionSummaryResource` (embedded, when a `Prediction` exists)
- Services: Not found in the implementation. (Uses Action class: `GetObservationAction`, and `ObservationRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Observation\Domain\AnalysisStatus` (`PENDING`, `PROCESSING`, `COMPLETED`, `FAILED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).
- Other: `App\Modules\Analysis\Application\Contracts\PredictionLookupContract` — the read-only cross-module interface used to resolve the `prediction` field.

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User, and an existing `observationId` within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Observation/ShowObservationTest.php`:

- Owner, Admin, and Member can all view a single observation, with `prediction` always `null` (no analysis has run in this test).
- An organization cannot view another organization's observation (404).
- A non-existent observation ID returns 404.
- An unauthenticated request cannot view an observation (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/observations`
- `POST /v1/observations`
- `GET /v1/agents/{agentId}/observations`
