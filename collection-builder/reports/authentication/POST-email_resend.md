# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/auth/email/resend`
- Endpoint Name: Resend Verification Email
- Purpose: Resends the email verification link to the authenticated User, if not already verified.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\EmailVerificationController`
- Controller Method: `resend`
- Middleware: `auth:api` (enclosing route group), `throttle:auth-email-resend`
- Throttle: `auth-email-resend` rate limiter — 5 requests per minute, keyed by request IP (`AppServiceProvider::registerRateLimiters`)
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

Not found in the implementation — no Form Request is used for this endpoint (controller takes a plain `Illuminate\Http\Request`).

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `ResendVerificationEmailAction::handle($user)`
- Business Rules:
  - If `$user->hasVerifiedEmail()` is already true, no notification is sent and the message `'Email already verified.'` is returned.
  - Otherwise, `$user->sendEmailVerificationNotification()` is called and the message `'Verification link sent.'` is returned.
- Database Operations: Not found in the implementation (no explicit read/write in the Action beyond the notification dispatch).
- Events: Not found in the implementation.
- Jobs: Not found in the implementation.
- Notifications: `$user->sendEmailVerificationNotification()` (Laravel's `MustVerifyEmail` trait — sends the `VerifyEmail` notification), only when the user is unverified.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` (`response()->json(['message' => ...])`), not an Eloquent API Resource.

## Response Structure

```
{
  "message": "Verification link sent." | "Email already verified."
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|401|No token provided, or an invalid/expired token — rendered via the global `AuthenticationException` render callback (`bootstrap/app.php`), which reuses `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`, `error.message = "Authentication failed."`, `error.details = []`.|
|429|Rate limit exceeded — `throttle:auth-email-resend` (5 requests/minute per IP). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Not found in the implementation.
- Audit Logs: Not found in the implementation.
- Security Logs: Not found in the implementation.
- Events: Not found in the implementation.
- Cache: Not found in the implementation.
- Notifications: Email verification notification (`VerifyEmail`) sent to the authenticated User, only if not already verified.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `ResendVerificationEmailAction`.)
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

A valid `access_token` for an authenticated User must be available.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/EmailVerificationTest.php`:

- An authenticated unverified user can request the verification link be resent (notification sent).
- Resend does nothing but confirm when the user is already verified (`'Email already verified.'`, no notification).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `GET /v1/auth/verify-email/{id}/{hash}`
- `POST /v1/auth/login`
- `POST /v1/auth/register`
