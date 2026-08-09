# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/me/change-password`
- Endpoint Name: Change Password
- Purpose: Changes the caller's own password after re-confirming their current password.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `changePassword`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — every authenticated Human manages their own password, no Role gate.

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

None.

## Request Body

- `current_password`
- `new_password`
- `new_password_confirmation` (implied by the `confirmed` rule on `new_password`)

## Validation Rules

From `ChangePasswordRequest::rules()`:

- `current_password`: `required`, `string`
- `new_password`: `required`, `confirmed`, `Password::defaults()`

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ChangePasswordAction::handle($user, $data)`
- Business Rules:
  - Operates only on `$request->user('api')` — the currently authenticated user.
  - If `Hash::check($data['current_password'], $user->password_hash)` fails, throws `InvalidCurrentPasswordException`.
  - Otherwise, `$user->forceFill(['password_hash' => Hash::make($data['new_password'])])->save()`.
  - Dispatches `UserPasswordChanged::dispatch($user->id, $user->organization_id)` — per the event's own doc-block, it "deliberately carries no password/hash data."
- Database Operations: `User::forceFill()->save()` on the `password_hash` column.
- Events: `UserPasswordChanged` (`userId`, `organizationId`).
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` (`response()->json(['status' => 'success', 'message' => 'Password changed successfully'])`), not an Eloquent API Resource.

## Response Structure

```
{
  "status": "success",
  "message": "Password changed successfully"
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|`current_password` does not match — rendered by `InvalidCurrentPasswordException::render()`: `error.code = UNAUTHORIZED`, `error.message = "The provided current password is incorrect."`, `error.details = []`. Also returned for no/invalid/expired token via the global `AuthenticationException` render callback (`AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`).|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by a missing `current_password`/`new_password` or a mismatched `new_password_confirmation`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Updates the User's `password_hash` column.
- Audit Logs: `App\Modules\Audit\Listeners\RecordPasswordChanged` listens for `UserPasswordChanged` and records an audit event via `RecordAuditEventAction` with `action: 'user.password_changed'`, `actorType: ActorType::User`, `resourceType: 'User'`.
- Security Logs: Not found in the implementation.
- Events: `UserPasswordChanged` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `ChangePasswordAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

Bearer token (`Authorization: Bearer {access_token}`), obtained from `POST /v1/auth/login`.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A valid `access_token` for an authenticated User must be available, and the caller must know their own current password.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/ProfileTest.php`:

- Any role can change their own password.
- `change-password` with an incorrect `current_password` returns 401 and updates nothing.
- `change-password` with mismatched confirmation returns 422.
- An unauthenticated request cannot change the password (401).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `PATCH /v1/me`
- `POST /v1/auth/login`
