# Endpoint Inspection Report

---

# Endpoint

- Method: PATCH
- Route: `/v1/me`
- Endpoint Name: Update Profile
- Purpose: Updates the caller's own `full_name`. No route parameter exists to target another user's record.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `updateProfile`
- Middleware: `auth:api`, `throttle:api` (applied to the enclosing route group in `routes/api.php`)
- Throttle: `api` rate limiter — 120 requests per minute, keyed by the authenticated user's ID (falls back to IP) (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: `api` (JWT — `config/auth.php` `guards.api.driver = 'jwt'`)
- Required Role: None — per the route's own comment: "No Role gate at all — every authenticated Human always manages their own profile."

---

# Request

## Headers

- `Authorization: Bearer {token}` — required by the `auth:api` middleware.

## Path Parameters

None.

## Query Parameters

None.

## Request Body

- `full_name`

Per `UpdateProfileRequest`'s own doc-block: `email` and `role` are never editable here — they are deliberately not listed as validated/fillable fields at all (not accepted-and-ignored, simply absent from the contract).

## Validation Rules

From `UpdateProfileRequest::rules()`:

- `full_name`: `required`, `string`, `max:255`

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `UpdateProfileAction::handle($user, $data)`
- Business Rules:
  - Operates only on `$request->user('api')` — the currently authenticated user — with no `user_id` parameter anywhere on the route to target another user.
  - Captures `$previousFullName` before updating.
  - `$user->update(['full_name' => $data['full_name']])`.
  - Dispatches `UserProfileUpdated::dispatch($user->id, $user->organization_id, $previousFullName, $user->full_name)`.
- Database Operations: `User::update()` on the `full_name` column.
- Events: `UserProfileUpdated` (`userId`, `organizationId`, `previousFullName`, `newFullName`).
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
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope. Triggered by a missing/malformed `full_name`.|
|429|Rate limit exceeded — `throttle:api` (120 requests/minute per authenticated user). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Updates the User's `full_name` column.
- Audit Logs: `App\Modules\Audit\Listeners\RecordProfileUpdated` listens for `UserProfileUpdated` and records an audit event via `RecordAuditEventAction` with `action: 'user.profile_updated'`, `actorType: ActorType::User`, `resourceType: 'User'`, `metadata: ['previous_full_name' => ..., 'new_full_name' => ...]`.
- Security Logs: Not found in the implementation.
- Events: `UserProfileUpdated` domain event.
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources:
  - `App\Modules\Authentication\Identity\Presentation\UserResource`
- Services: Not found in the implementation. (Uses Action class: `UpdateProfileAction`.)
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

Existing coverage found in `backend/tests/Feature/Auth/ProfileTest.php`:

- Any role (owner/admin/member) can update their own `full_name`.
- `PATCH /me` attempting to set `email` or `role` has no effect.
- An unauthenticated request cannot update the profile (401).
- There is no route parameter allowing a user to update another user's profile.

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/me/change-password`
- `GET /v1/auth/me`
