# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/auth/logout`
- Endpoint Name: Logout
- Purpose: Logs out the authenticated User (client-side only in V1 — no server-side token invalidation).

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `logout`
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
- Action: `LogoutUserAction::handle()`
- Business Rules:
  - Resolves the current User via `auth('api')->user()`.
  - Dispatches `UserLoggedOut::dispatch($user->id, $user->organization_id)`.
  - Per the Action's own doc-block: "Logout is client-side only in V1 — no server-side token blacklist exists yet, matching 04-jwt.md §9."
- Database Operations: Not found in the implementation.
- Events: `UserLoggedOut` domain event (`userId`, `organizationId`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` (`response()->json(['message' => 'Logged out.'])`), not an Eloquent API Resource.

## Response Structure

```
{
  "message": "Logged out."
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
- Audit Logs: `App\Modules\Audit\Listeners\RecordUserLoggedOut` listens for `UserLoggedOut` and records an audit event via `RecordAuditEventAction` with `action: 'user.logged_out'`, `actorType: ActorType::User`, `resourceType: 'User'`.
- Security Logs: Not found in the implementation.
- Events: `UserLoggedOut` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `LogoutUserAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login` or `POST /v1/auth/refresh`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/MeLogoutRefreshTest.php`:

- Logout succeeds for an authenticated user.
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
- `GET /v1/auth/me`
