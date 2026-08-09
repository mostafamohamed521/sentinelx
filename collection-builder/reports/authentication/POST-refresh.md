# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/auth/refresh`
- Endpoint Name: Refresh
- Purpose: Issues a new JWT bearer token from the caller's current token.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `refresh`
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
- Action: `RefreshTokenAction::handle()`, which returns `JWTAuth::parseToken()->refresh()`.
- Business Rules: Not found in the implementation beyond delegating to the JWT library's own refresh logic.
- Database Operations: Not found in the implementation.
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` from `AuthController::tokenResponse()`, not an Eloquent API Resource.

## Response Structure

```
{
  "access_token": "...",
  "token_type": "bearer",
  "expires_in": ... (JWT TTL in seconds, from auth('api')->factory()->getTTL() * 60; config/jwt.php 'ttl' defaults to 60 minutes)
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

- Models: Not found in the implementation (specific to this endpoint's own logic).
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `RefreshTokenAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation.
- Traits: Not found in the implementation.

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login` or a prior `POST /v1/auth/refresh`.

## Variables To Save

- `access_token`

## Pre-request Requirements

A valid (not-yet-expired, per JWT library semantics) `access_token` must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/MeLogoutRefreshTest.php`:

- `refresh` returns a new bearer token.
- Protected auth endpoints reject requests with no token.
- Protected auth endpoints reject an invalid token.

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/auth/login`
- `GET /v1/auth/me`
- `POST /v1/auth/logout`
