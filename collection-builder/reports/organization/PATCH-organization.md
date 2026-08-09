# Endpoint Inspection Report

---

# Endpoint

- Method: PATCH
- Route: `/v1/organization`
- Endpoint Name: Update Organization
- Purpose: Updates the caller's own Organization's `name`. Owner role only.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Organization\API\Controllers\OrganizationController`
- Controller Method: `update`
- Middleware: `auth:api`, `throttle:api` (enclosing route group), `App\Modules\Organization\API\Middleware\EnsureOwnerRole` (route-level, aliased as `OrganizationEnsureOwnerRole` in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: `OWNER` — enforced by `Organization\API\Middleware\EnsureOwnerRole`, which checks `$request->user('api')->role?->value === 'OWNER'`. Per that middleware's own doc-block: "A dedicated copy rather than reusing Agent's `EnsureOwnerRole`: Organization sits beneath Agent in the dependency chain (Agent -> Organization), so Organization must never import anything from Agent."

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

## Validation Rules

From `UpdateOrganizationRequest::rules()`:

- `name`: `required`, `string`, `max:255`
- `slug`: `prohibited` — per the FormRequest's own doc-block, "`slug` and `status` are explicitly rejected (422), never silently stripped."
- `status`: `prohibited` — same treatment.

Role authorization is deliberately not checked in `authorize()` (always returns `true`) — per the FormRequest's own doc-block, "Role gate (Owner only) is enforced by `Organization\API\Middleware\EnsureOwnerRole` at the route level, not here — consistent with every other module's FormRequest, which always returns true and leaves Role checks to routing/middleware."

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `UpdateOrganizationAction::handle($organizationId, $data, $actorUserId)`
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT, never accepted from the request. Per the Action's own comment: "`organizationId` always comes from the AuthenticatedIdentity, so this can never legitimately miss" — no not-found check is performed on the lookup.
  - Captures `$previousName` before updating.
  - `OrganizationRepository::update()` persists only `name` and refreshes the model.
  - Dispatches `OrganizationUpdated::dispatch($organizationId, $actorUserId, $previousName, $organization->name)`.
- Database Operations: `Organization::find($organizationId)` (`OrganizationRepository::findById()`); `$organization->update(['name' => ...])` then `$organization->refresh()` (`OrganizationRepository::update()`).
- Events: `OrganizationUpdated` (`organizationId`, `actorUserId`, `previousName`, `newName`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Organization\Presentation\OrganizationResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "name": ...,
    "slug": ...,
    "status": ...,
    "created_at": ...,
    "updated_at": ...
  }
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`.|
|403|Caller's role is not `OWNER` — rendered by `OrganizationForbiddenException::render()`: `error.code = FORBIDDEN`, `error.message = "You do not have permission to perform this action."`|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by a missing `name`, or a `slug`/`status` field present in the body (both `prohibited`).|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Updates the Organization's `name` column.
- Audit Logs: `App\Modules\Audit\Listeners\RecordOrganizationUpdated` listens for `OrganizationUpdated` and records an audit event via `RecordAuditEventAction` with `action: 'organization.updated'`, `actorType: ActorType::User`, `resourceType: 'Organization'`, `metadata: ['previous_name' => ..., 'new_name' => ...]`.
- Security Logs: Not found in the implementation.
- Events: `OrganizationUpdated` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Organization\Infrastructure\Persistence\Organization`
- Resources:
  - `App\Modules\Organization\Presentation\OrganizationResource`
- Services: Not found in the implementation. (Uses Action class: `UpdateOrganizationAction`, and `OrganizationRepository` for persistence access.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic — `status` is `OrganizationStatus`, immutable via this endpoint).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`) for a User whose `role` is `OWNER`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated Owner-role User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Organization/OrganizationTest.php`:

- An owner can update the organization name.
- `PATCH /organization` attempting to set `slug` is rejected with 422, not silently stripped.
- `PATCH /organization` attempting to set `status` is rejected with 422, not silently stripped.
- An admin cannot update the organization (403).
- A member cannot update the organization (403).
- An unauthenticated request cannot update the organization (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/organization`
