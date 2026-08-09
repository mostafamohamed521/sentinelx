# 05 — Profile

> A small, additive extension to the already-frozen Authentication module (Stage 1). No new module, no new folder of its own conceptually — documented here because it ships in this Sprint per the roadmap.

---

## 1. What Already Exists (Stage 1, Unchanged)

```text
GET /api/v1/me    ← already built, per module-responsibilities.md §2's own listed
                       responsibility "Current User (/me)" — returns the caller's
                       own User record (id, email, full_name, role, stage — the
                       latter per the Cinematch precedent example this series was
                       originally built from)
```

**This Sprint does not touch this endpoint.**

---

## 2. What This Sprint Adds

```text
PATCH /api/v1/me                 ← update own full_name
POST  /api/v1/me/change-password   ← change own password
```

---

## 3. Why Password Change Is a Separate Endpoint From General Profile Updates

Consistent with [`02-auth/05-api-keys.md`](../02-auth/02-auth/05-api-keys.md)'s own precedent of treating credential-adjacent actions (rotate) as distinct, deliberate operations rather than folding them into a general "update" — and consistent with Authentication's own already-frozen responsibility list, which lists `Reset Password` as a distinct item from any general "update user" capability. Password changes should require re-confirmation of the current password (a `current_password` field in the request), which is meaningfully different validation from a simple `full_name` rename — bundling them into one endpoint would either force every profile edit to also demand a password re-entry (poor UX, no documented reason for it) or create a conditionally-required field, which is exactly the kind of validation complexity a dedicated endpoint avoids.

---

## 4. No Self-Service Role or Email Change

```text
role    ← never editable via these endpoints — Role changes belong to Team
             Management (not built, not scoped for V1 anywhere), and are certainly
             never a SELF-service action (a User granting themselves a higher Role
             would be an obvious security hole)
email    ← never editable via these endpoints in V1 — no frozen document describes
             an email-change flow (which would need re-verification, per the
             already-frozen email_verified_at column's own semantics), and inventing
             one is out of scope for this Sprint
```

---

## 5. Field-Level Rules

| Field | Endpoint | Rule |
|-------|----------|------|
| `full_name` | `PATCH /me` | Required if present in the body; 1-255 chars, same shape as registration's own validation |
| `current_password` | `POST /me/change-password` | Required; must match the caller's existing `password_hash` or the request is rejected |
| `new_password` | `POST /me/change-password` | Required; same strength rule already frozen for registration (min 8 chars, per the Cinematch precedent's `RegisterRequest`) |
| `new_password_confirmation` | `POST /me/change-password` | Required, must match `new_password` |

---

## 6. Response Shapes

```json
// PATCH /me → 200 OK
{ "data": { "id": "...", "email": "...", "full_name": "Ahmed Updated", "role": "OWNER" } }

// POST /me/change-password → 200 OK
{ "status": "success", "message": "Password changed successfully" }
```

**`POST /me/change-password` never returns a new JWT automatically** — no frozen document specifies whether existing sessions/tokens should be invalidated on password change. Given [`02-auth/06-authorization.md`](../02-auth/02-auth/06-authorization.md) §8's own established reasoning (*"the JWT carries only the Identity ID... the system fetches the Role from the database on every check"*), an already-issued JWT remains valid until its natural expiry even after a password change, in V1 — flagged here as a real, if minor, security consideration for a future Sprint (forced re-authentication on password change), not solved in this one.

---

## 7. Audit Integration

Both actions dispatch an event Audit listens to (per [`03-audit-logging.md`](./03-audit-logging.md)):

```text
PATCH /me                  → dispatches UserProfileUpdated(userId, organizationId)
POST /me/change-password     → dispatches UserPasswordChanged(userId, organizationId)
                                  — metadata never includes the actual password or
                                  hash, only the fact that a change occurred
```
