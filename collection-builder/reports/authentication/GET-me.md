# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/auth/me`
- Endpoint Name: Me
- Purpose: Returns the currently authenticated User.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `me`
- Middleware: `auth:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: Not found in the implementation for this specific route (falls under the group wrapped by `auth:api`, not `throttle:auth-*`; no `throttle` middleware is attached to this group in `routes/api.php`).
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: Not found in the implementation.

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
- Action: `GetCurrentUserAction::handle()`, which returns `auth('api')->user()`.
- Business Rules: Not found in the implementation beyond guard-level authentication (resolving the User bound to the current JWT).
- Database Operations: Not found in the implementation (user resolution is handled by the `auth:api` guard/provider, not explicit code in the Action).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

`App\Modules\Authentication\Identity\Presentation\UserResource`

## Response Structure

```
{
  "data": {
    "id": ...,
    "organization_id": ...,
    "full_name": ...,
    "email": ...,
    "role": ...,
    "status": ...,
    "email_verified_at": ...,
    "last_login_at": ...,
    "created_at": ...
  }
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`, `error.message = "Authentication failed."`, `error.details = []`.|

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
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources:
  - `App\Modules\Authentication\Identity\Presentation\UserResource`
- Services: Not found in the implementation. (Uses Action class: `GetCurrentUserAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login` or `POST /v1/auth/refresh`.

## Variables To Save

- `data.id`

## Pre-request Requirements

A valid, non-expired `access_token` for an authenticated User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/MeLogoutRefreshTest.php`:

- `me` returns the authenticated user for a valid token.
- `me` never exposes `password_hash`.
- Protected auth endpoints reject requests with no token.
- Protected auth endpoints reject an invalid token.

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/auth/login`
- `POST /v1/auth/refresh`
- `POST /v1/auth/logout`
