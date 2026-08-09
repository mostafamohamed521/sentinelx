# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/auth/login`
- Endpoint Name: Login
- Purpose: Authenticates a User by email/password and issues a JWT bearer token.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `login`
- Middleware: `throttle:auth-login`
- Throttle: `auth-login` rate limiter — 5 requests per minute, keyed by request IP (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: None — `LoginRequest::authorize()` returns `true` and no `auth` middleware is applied to this route.
- Required Role: Not found in the implementation.

---

# Request

## Headers

Not found in the implementation.

## Path Parameters

None.

## Query Parameters

None.

## Request Body

- `email`
- `password`

## Validation Rules

From `LoginRequest::rules()`:

- `email`: `required`, `string`, `email`
- `password`: `required`, `string`

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation. The controller passes `$request->validated()` directly as an array to the Action.
- Action: `LoginUserAction::handle()`
- Business Rules:
  - Looks up the User by `email`; if not found or `Hash::check()` against `password_hash` fails, throws `AuthenticationFailedException('Invalid credentials.')`.
  - If `$user->status !== UserStatus::Active`, throws `AuthenticationFailedException('User is not active.')`.
  - If `! $user->hasVerifiedEmail()`, throws `AuthenticationFailedException('Email is not verified.')`.
  - If `$user->organization->status !== OrganizationStatus::Active`, throws `AuthenticationFailedException('Organization is not active.')`.
  - On success, issues a JWT via `JWTAuth::fromUser($user)`.
  - Every failure reason is logged via `Log::warning()` with the attempted email or user id, but the exception message passed to `AuthenticationFailedException` is never included in the HTTP response (see `AuthenticationFailedException::render()`).
- Database Operations: `User::where('email', ...)->first()`; on success, `$user->forceFill(['last_login_at' => now()])->save()`.
- Events: `UserLoggedIn::dispatch($user->id, $user->organization_id)`, dispatched after the token is issued and `last_login_at` is saved.
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
|401|Authentication failure — unknown email, wrong password, inactive user, unverified email, or suspended organization. All causes render the identical generic envelope via `AuthenticationFailedException::render()`: `error.code = AUTHENTICATION_FAILED`, `error.message = "Authentication failed."`, `error.details = []`.|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by a missing/malformed `email` or missing `password`.|
|429|Rate limit exceeded — `throttle:auth-login` (5 requests/minute per IP). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Updates the User's `last_login_at` column on successful login. No changes on failure.
- Audit Logs: `App\Modules\Audit\Listeners\RecordUserLoggedIn` listens for `UserLoggedIn` and records an audit event via `RecordAuditEventAction` with `action: 'user.logged_in'`, `actorType: ActorType::User`, `resourceType: 'User'`.
- Security Logs: Not found in the implementation.
- Events: `UserLoggedIn` domain event (`userId`, `organizationId`).
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
  - `App\Modules\Organization\Infrastructure\Persistence\Organization` (accessed via `$user->organization`)
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `LoginUserAction`.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Authentication\Identity\Domain\UserStatus` (`ACTIVE`, `DISABLED`)
  - `App\Modules\Organization\Domain\OrganizationStatus`
- Traits: Not found in the implementation (specific to this endpoint's flow).

---

# Postman Collection Notes

## Authorization

None required — public, unauthenticated endpoint.

## Variables To Save

- `access_token`

## Pre-request Requirements

A verified, active User belonging to an active Organization must already exist (e.g. created via `POST /v1/auth/register` and email-verified).

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/LoginTest.php`:

- A verified, active user can log in and receives a bearer token.
- Login updates `last_login_at`.
- Login fails with a generic 401 message for a wrong password.
- Login fails with a generic 401 message for an unknown email.
- Login fails with a generic 401 message for a disabled user.
- Login fails with a generic 401 message for a user whose Organization is suspended.
- Login fails with a generic 401 message for an unverified user.
- Login is rate limited (429 after 5 requests/minute).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/auth/register`
- `POST /v1/auth/refresh`
- `POST /v1/auth/logout`
- `GET /v1/auth/me`
