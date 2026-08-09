# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/organization`
- Endpoint Name: Show Organization
- Purpose: Returns the caller's own Organization.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Organization\API\Controllers\OrganizationController`
- Controller Method: `show`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the controller's own comment, "Owner, Admin, Member" all have access, and per `routes/api.php`'s own comment, "GET is Owner/Admin/Member (harmless to view)."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

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
- Action: Not found in the implementation — the controller calls `OrganizationRepository::findById()` directly, with no intervening Action class.
- Business Rules:
  - `organization_id` is always server-derived from the authenticated Human's JWT (`OrganizationController::organizationId()`), never accepted from the request. Per `OrganizationRepository`'s own doc-block: "No cross-tenant surface exists on these methods — every caller already knows its own `organizationId` from the AuthenticatedIdentity, and there is no path/query parameter anywhere that could name a different Organization."
- Database Operations: `Organization::find($organizationId)` (`OrganizationRepository::findById()`).
- Events: Not found in the implementation.
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
  - `App\Modules\Organization\Infrastructure\Persistence\Organization`
- Resources:
  - `App\Modules\Organization\Presentation\OrganizationResource`
- Services: Not found in the implementation. (Uses `OrganizationRepository` directly for persistence access; no Action class for this endpoint.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic — `status` is `OrganizationStatus`, owned by the Organization module's Domain layer).
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

Existing coverage found in `backend/tests/Feature/Organization/OrganizationTest.php`:

- Owner, Admin, and Member can all view their own organization.
- An unauthenticated request cannot view the organization (401).
- A user always sees their own organization, never another (data isolation).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `PATCH /v1/organization`
