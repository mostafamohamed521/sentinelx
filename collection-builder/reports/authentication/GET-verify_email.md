# Endpoint Inspection Report

---

# Endpoint

- Method: GET
- Route: `/v1/auth/verify-email/{id}/{hash}`
- Endpoint Name: Verify Email
- Purpose: Marks a User's email as verified via a signed, expiring verification link.

---

# Routing

- API Version: v1
- Controller: `App\Modules\Authentication\Identity\API\Controllers\EmailVerificationController`
- Controller Method: `verify`
- Middleware: `signed`
- Throttle: Not found in the implementation for this specific route (no `throttle` middleware attached in `routes/api.php`).
- Authentication Guard: None — no `auth` middleware is applied; the `signed` middleware validates the URL signature/expiry instead.
- Required Role: Not found in the implementation.
- Route Name: `verification.verify`

---

# Request

## Headers

Not found in the implementation.

## Path Parameters

- `id` — the target User's ID
- `hash` — `sha1($user->email)`, validated against the User's current email

## Query Parameters

- `signature` and `expires` — required by Laravel's `signed` middleware (part of the temporary signed URL mechanism), not read directly by the controller/Action.

## Request Body

None.

## Validation Rules

Not found in the implementation — no Form Request is used for this endpoint. Validation is performed by the `signed` middleware (URL signature/expiry) and, inside `VerifyEmailAction`, a `hash_equals()` comparison of the `hash` parameter against `sha1($user->getEmailForVerification())`.

---

# Processing

- Service: Not found in the implementation.
- DTO: Not found in the implementation.
- Action: `VerifyEmailAction::handle($id, $hash)`
- Business Rules:
  - `User::findOrFail($id)` — 404 if the user does not exist.
  - If `hash_equals(sha1($user->getEmailForVerification()), $hash)` fails, `abort(403, 'Invalid verification link.')`.
  - If `$user->hasVerifiedEmail()` is already true, returns `'Email already verified.'` without further changes (idempotent).
  - Otherwise, calls `$user->markEmailAsVerified()`, fires `Illuminate\Auth\Events\Verified`, and returns `'Email verified successfully.'`.
  - Per the Action's own doc-block: the `signed` route middleware already validates the URL's signature and expiry before this Action runs; this Action only checks the hash against the target user's current email, then persists to `email_verified_at`.
- Database Operations: `User::findOrFail($id)`; on first verification, persists `email_verified_at` via `markEmailAsVerified()`.
- Events: `Illuminate\Auth\Events\Verified` fired on first successful verification.
- Jobs: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Response

## Success Status Code

200

## Response Resource

Not found in the implementation — response is a hand-built `JsonResponse` (`response()->json(['message' => ...])`), not an Eloquent API Resource.

## Response Structure

```
{
  "message": "Email verified successfully." | "Email already verified."
}
```

---

# Error Responses

| Status | Condition |
|---------|-----------|
|403|The verification link's hash does not match the target user's current email (`abort(403, 'Invalid verification link.')`), or the signed URL is expired/tampered (rejected by the `signed` middleware).|
|404|`User::findOrFail($id)` — no user exists for the given `id`.|

Only include implemented responses.

---

# Side Effects

- Database Changes: Sets `email_verified_at` on the User, only on first successful verification.
- Audit Logs: Not found in the implementation — no listener found for `Illuminate\Auth\Events\Verified` in this codebase.
- Security Logs: Not found in the implementation.
- Events: `Illuminate\Auth\Events\Verified`
- Cache: Not found in the implementation.
- Notifications: Not found in the implementation.

---

# Dependencies

- Models:
  - `App\Modules\Authentication\Identity\Infrastructure\Persistence\User`
- Resources: Not found in the implementation.
- Services: Not found in the implementation. (Uses Action class: `VerifyEmailAction`.)
- Policies: Not found in the implementation.
- Enums: Not found in the implementation (specific to this endpoint's own logic).
- Traits: Not found in the implementation (specific to this endpoint's own logic).

---

# Postman Collection Notes

## Authorization

None — access is controlled by the URL's signature (`signed` middleware), not a bearer token.

## Variables To Save

Not found in the implementation.

## Pre-request Requirements

A signed URL must be generated for the target user's `id` and email `hash` (e.g. via `URL::temporarySignedRoute('verification.verify', ...)`, as seen in the existing test suite) — this cannot be constructed from the API alone.

## Post-request Automation

Not found in the implementation.

## Suggested Test Cases

Existing coverage found in `backend/tests/Feature/Auth/EmailVerificationTest.php`:

- Following a valid signed verification link marks the email as verified.
- Visiting an already-consumed verification link twice is idempotent (`'Email already verified.'`).
- A verified user can log in.
- An expired verification link is rejected (403).
- A verification link with a tampered hash is rejected (403).

## Collection Folder

Not found in the implementation.

## Execution Order

Not found in the implementation.

---

# Related Endpoints

- `POST /v1/auth/register`
- `POST /v1/auth/email/resend`
- `POST /v1/auth/login`
