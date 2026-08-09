# Endpoint Inspection Report

---

# Endpoint

- Method: POST
- Route: `/v1/auth/register`
- Endpoint Name: Register
- Purpose: Creates a new Organization plus its first User, who is always assigned the Owner role.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\AuthController`
- Controller Method: `register`
- Middleware: `throttle:auth-register`
- Throttle: `auth-register` rate limiter — 10 requests per minute, keyed by request IP (`AppServiceProvider::registerRateLimiters`)
- Authentication Guard: None — `RegisterRequest::authorize()` returns `true` and no `auth` middleware is applied to this route.
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

- `organization_name`
- `full_name`
- `email`
- `password`
- `password_confirmation` (implied by the `confirmed` validation rule on `password`)

## Validation Rules

From `RegisterRequest::rules()`:

- `organization_name`: `required`, `string`, `max:255`
- `full_name`: `required`, `string`, `max:255`
- `email`: `required`, `string`, `email`, `max:255`, `unique:users,email`
- `password`: `required`, `confirmed`, `Password::defaults()`

Custom messages defined in `RegisterRequest::messages()`:

- `organization_name.required`: "Please provide a name for your organization."
- `email.unique`: "An account with this email already exists."

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation. The controller passes `$request->validated()` directly as an array to the Action.
- Action: `RegisterUserAction::handle()`, which delegates organization creation to `CreateOrganizationAction::handle()`.
- Business Rules:
  - Registration always creates a new Organization together with its first User.
  - The first User is always created with role `UserRole::Owner` and status `UserStatus::Active`.
  - The Organization slug is generated from `organization_name` via `Str::slug()`, with a numeric suffix loop (`CreateOrganizationAction::uniqueSlug()`) appended until unique.
  - Password is hashed via `Hash::make()` before storage (`password_hash` column).
- Database Operations: Wrapped in `DB::transaction()`:
  - `Organization::create()` (name, slug, status)
  - `$organization->users()->create()` (full_name, email, password_hash, role, status)
- Events: `UserRegistered::dispatch($user->id, $user->organization_id)`, dispatched after the transaction commits.
- Jobs: Not found in the implementation.
- Notifications: `$user->sendEmailVerificationNotification()` is called inside the transaction (Laravel's `MustVerifyEmail` trait — sends the `VerifyEmail` notification).

---

# Response

## Success Status Code

201

## Response Resource

`App\Modules\Authentication\Identity\Presentation\UserResource`, wrapped with `->additional(['message' => 'Organization created. Please check your email to verify your account.'])`.

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
  },
  "message": "Organization created. Please check your email to verify your account."
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|422|Validation failure — returned via the global `ValidationException` render callback (`bootstrap/app.php`) as the nested `VALIDATION_ERROR` envelope (`error.code`, `error.message`, `error.details`). Triggered e.g. by a duplicate `email` or mismatched `password`/`password_confirmation`.|
|429|Rate limit exceeded — `throttle:auth-register` (10 requests/minute per IP). Rendered by Laravel's default throttle response (not customized for this route).|

Only include implemented responses.

---

# Side Effects

- Database Changes: Inserts one `organizations` row and one `users` row.
- Audit Logs: `App\Modules\Audit\Listeners\RecordUserRegistered` listens for `UserRegistered` and records an audit event via `RecordAuditEventAction` with `action: 'user.registered'`, `actorType: ActorType::User`, `resourceType: 'User'`.
- Security Logs: Not found in the implementation.
- Events: `UserRegistered` domain event (`userId`, `organizationId`).
- Cache: Not found in the implementation.
- Notifications: Email verification notification (`VerifyEmail`) sent to the newly created User.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
  - `App\Modules\Organization\Infrastructure\Persistence\Organization`
- Resources:
  - `App\Modules\Authentication\Identity\Presentation\UserResource`
- Services: Not found in the implementation. (Uses Action classes: `RegisterUserAction`, `CreateOrganizationAction`.)
- Policies: Not found in the implementation.
- Enums:
  - `App\Modules\Authentication\Identity\Domain\UserRole` (`OWNER`, `ADMIN`, `MEMBER`)
  - `App\Modules\Authentication\Identity\Domain\UserStatus` (`ACTIVE`, `DISABLED`)
  - `App\Modules\Organization\Domain\OrganizationStatus`
- Traits: `HasFactory`, `HasUuids`, `MustVerifyEmail`, `Notifiable` (on the `User` model).

---

# Postman Collection Notes

## Authorization

None required — public, unauthenticated endpoint.

## Variables To Save

- `data.id` (User ID)
- `data.organization_id`

## Pre-request Requirements

None.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/RegisterTest.php`:

- Registering creates an organization and an owner user, and sends a verification email.
- Registration response never exposes `password_hash`.
- Registration fails when the email is already taken (422, `VALIDATION_ERROR`).
- Registration fails when the password confirmation does not match (422, `VALIDATION_ERROR`).
- Two organizations get distinct slugs even with the same name.

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/auth/login`
- `GET /v1/auth/verify-email/{id}/{hash}`
- `POST /v1/auth/email/resend`
