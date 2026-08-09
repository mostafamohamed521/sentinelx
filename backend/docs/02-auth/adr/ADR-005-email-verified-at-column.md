# ADR-005: Email Verification Is Tracked on an Additive `email_verified_at` Column, Never by Expanding `UserStatus`

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Affects** | `users` table (one new nullable column); [`03-authentication-flow.md`](../03-authentication-flow.md); [`02-domain.md`](../02-domain.md); [`contracts/auth-errors.md`](../contracts/auth-errors.md) |

---

## Context

`03-authentication-flow.md` §3 requires that a newly registered Human verify their email before they can log in. `UserStatus` (`users.status`) is a deliberately minimal, two-value enum — `ACTIVE` / `DISABLED` — and must stay that way: `DISABLED` means exactly one thing, an administratively deactivated account, the replacement for deletion. It must never be reinterpreted to also mean "registered but not yet verified," and no third or fourth status value (`PENDING VERIFICATION`, `SUSPENDED`, etc.) is introduced either — expanding the enum to carry verification state would overload a single column with two unrelated concerns and silently reopen a decision that is explicitly frozen (see [`01-database/schema/enums.md`](../../01-database/schema/enums.md#3-userstatus--table-usersstatus)).

---

## Decision

Add one nullable, additive column to the existing `users` table:

```text
users
──────────────────────
...(existing columns unchanged)...
email_verified_at      Timestamp, Nullable
...
```

- `NULL` → the account has not completed email verification.
- Non-`NULL` (set once, to the verification time) → the account is verified.

This column is orthogonal to `status`. A user can be `ACTIVE` and unverified at the same time (during the registration window), and `DISABLED` never implies, and is never implied by, verification state. `UserStatus` remains exactly `ACTIVE` / `DISABLED` — this ADR does not touch it.

### Login Gate

```text
Register → email_verified_at = NULL, status = ACTIVE
Verify Email (signed URL) → email_verified_at = now()
Login → rejected while email_verified_at IS NULL
```

The verification **mechanism** — a signed, expiring URL carrying the user's ID, validated by signature and expiry — is unaffected by this decision; this ADR only specifies where its result is persisted.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|---|---|
| Reinterpret `DISABLED` to also mean unverified | Overloads one frozen enum value with two unrelated meanings — a hidden architectural decision disguised as a wording fix. |
| Add a third/fourth `UserStatus` value (`PENDING_VERIFICATION`, `SUSPENDED`, `ARCHIVED`) | Changes the meaning and cardinality of an already-frozen enum column that Authorization and other checks depend on being exactly two-valued; a nullable timestamp is strictly additive, an enum value is not. |
| Stateless-only signed link, no persisted flag | Cannot answer "is this account currently verified?" outside the moment the link is clicked (e.g. on every login) — the flag is required to gate login at all. |

---

## Consequences

- ✅ `03-authentication-flow.md` §3's documented flow (`Register → Verify Email → Login`) is fully implementable without touching `UserStatus`.
- ✅ `UserStatus` stays exactly `ACTIVE` / `DISABLED`, matching [`01-database/schema/enums.md`](../../01-database/schema/enums.md) and [`entities.md`](../../01-database/schema/entities.md) — no drift between the two documentation sets on this point.
- ✅ `contracts/auth-errors.md`'s "unverified email" case is a distinct, real failure condition, independent of the `DISABLED` cause.
- ⚠️ Any diagram or prose describing Human identity state must show exactly two states (`ACTIVE` ⇄ `DISABLED`) — email verification is never drawn as a state transition on that diagram, since it isn't one.
