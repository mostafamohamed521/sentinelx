# Endpoint Inspection Report

---

# Endpoint

- Method: PATCH
- Route: `/v1/alerts/{alertId}/resolve`
- Endpoint Name: Resolve Alert
- Purpose: Transitions an `OPEN` or `ACKNOWLEDGED` Alert to `RESOLVED`, recording the acting User and timestamp. `RESOLVED` is terminal.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Alert\API\Controllers\AlertController`
- Controller Method: `resolve`
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

None — per the controller's own comment (shared with `acknowledge`), "`resolved_by` ... [is] always the authenticated User's own ID — never accepted from a request body." Any body sent (e.g. a `resolved_by` field) is ignored entirely; the controller never reads the request body for this action.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ResolveAlertAction::handle($organizationId, $alertId, $userId)`
- Business Rules:
  - `organization_id` and the acting `userId` are always server-derived from the authenticated Human's JWT (`AlertController::organizationId()` / `userId()`), never accepted from the request.
  - `AlertRepository::findById($alertId, $organizationId)` scopes the lookup; throws `AlertNotFoundException` if not found (same indistinguishable-by-design 404 as `show`/`acknowledge`).
  - `AlertPolicy::ensureCanBeResolved($alert->status)` throws `AlertAlreadyResolvedException` only if the Alert's current status is already `RESOLVED`. Per the policy's own doc-block: "Callable from either OPEN or ACKNOWLEDGED — skip-ahead is allowed, no business rule requires acknowledging first." Per the exception's own doc-block: "RESOLVED is terminal in V1 (ADR-002) — there is no transition out of it, including a second resolve."
  - `AlertRepository::resolve()` sets `status = AlertStatus::Resolved`, `resolved_at = now()`, `resolved_by = $userId`. Resolving directly from `OPEN` leaves `acknowledged_at` untouched (`null`).
  - Dispatches `AlertResolved::dispatch($alertId, $organizationId, $userId)`.
- Database Operations: `Alert::query()->whereHas('prediction.observation', ...)->where('id', ...)->first()` (lookup, via `AlertRepository::findById()`); `Alert::query()->where('id', $alertId)->update(['status' => Resolved, 'resolved_at' => ..., 'resolved_by' => ...])` then `Alert::findOrFail($alertId)` (`AlertRepository::resolve()`).
- Events: `AlertResolved` (`alertId`, `organizationId`, `actorUserId`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` from `AlertController::resolve()`, not an Eloquent API Resource.

## Response Structure

```
{
  "data": {
    "id": ...,
    "status": "RESOLVED",
    "resolved_at": ...,
    "resolved_by": ...
  }
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, an invalid/expired token, or an Agent (API Key) with no JWT — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|404|No Alert exists with the given `alertId` in the caller's Organization (including an Alert that exists but belongs to a different Organization) — rendered by `AlertNotFoundException::render()`: `error.code = NOT_FOUND`, `error.message = "Alert not found."`|
|409|The Alert's status is already `RESOLVED` — rendered by `AlertAlreadyResolvedException::render()`: `error.code = CONFLICT`, `error.message = "This Alert has already been resolved."`|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Sets the Alert's `status`, `resolved_at`, and `resolved_by` columns.
- Audit Logs: `App\Modules\Audit\Listeners\RecordAlertResolved` listens for `AlertResolved` and records an audit event via `RecordAuditEventAction` with `action: 'alert.resolved'`, `actorType: ActorType::User`, `resourceType: 'Alert'`.
- Security Logs: Not found in the implementation.
- Events: `AlertResolved` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Alert\Infrastructure\Persistence\Alert`
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `ResolveAlertAction`, and `AlertRepository` for persistence access.)
- Policies:
  - `App\Modules\Alert\Domain\AlertPolicy` (`ensureCanBeResolved()`)
- Enums:
  - `App\Modules\Alert\Domain\AlertStatus` (`OPEN`, `ACKNOWLEDGED`, `RESOLVED`)
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User, and an existing `alertId` (status `OPEN` or `ACKNOWLEDGED`) within that User's Organization.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Alert/ResolveAlertTest.php`:

- Owner, Admin, and Member can each resolve an `OPEN` or `ACKNOWLEDGED` alert.
- Resolving directly from `OPEN` (skip-ahead) succeeds without requiring acknowledge first, and `acknowledged_at` stays `null`.
- `resolved_by` always matches the authenticated user's own ID, never client-suppliable.
- Resolving an already-resolved alert returns 409.
- An organization cannot resolve another organization's alert (404, data isolation).
- An unauthenticated request cannot resolve an alert (401).
- An Agent (API Key) cannot resolve an alert (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/alerts/{alertId}`
- `PATCH /v1/alerts/{alertId}/acknowledge`
- `GET /v1/alerts`
