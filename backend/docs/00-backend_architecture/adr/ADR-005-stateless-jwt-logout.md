# ADR-005: Logout Is Client-Side Only in V1; Server-Side Token Revocation Is Deferred

| | |
|---|---|
| **Status** | ✅ Accepted (Documentation Baseline v2.0) |
| **Conflict Source** | Cross-Review, Conflict 5 |
| **Affects** | The Authentication module's `Logout` behavior; `09-api-reference/02-AUTH_API.md` in `docs.zip` |

---

## Context

`docs.zip`'s `09-api-reference/02-AUTH_API.md` specifies `POST /auth/logout` as: *"Invalidates the current access token,"* which implies server-side token tracking and revocation.

The Authentication design series (9 sessions, already fully documented — see the Authentication documentation's [`04-jwt.md`](../../authentication/04-jwt.md)) explicitly decided the opposite: JWT is Stateless, never stored server-side, and Logout deletes the token client-side only in V1.

---

## Decision

**The Stateless JWT design is confirmed as correct for V1.** `Logout` removes the token from the client only. No server-side blacklist, revocation list, or token store exists in V1.

---

## Rationale

### The Old Spec Predates the JWT Design Session
`AUTH_API.md` was written generically, before the dedicated JWT Design session took place. It reflects a plausible default assumption ("logout should invalidate the token"), not a deliberate architectural decision made with the tradeoffs in view.

### Stateless Is the Better Fit for the MVP
A stateless JWT requires zero server-side session storage, keeps every authenticated request's cost constant regardless of how many users are logged in, and avoids introducing a blacklist/cache infrastructure requirement before it's actually needed. This directly supports the project's "Production-Level, without Over Engineering" principle.

### This Is Already Documented, Not New
This decision was already fully specified in the Authentication documentation (see `04-jwt.md`, Section 9, and `contracts/jwt-claims.md`, Section 6) — this ADR exists specifically to record, for the Backend Architecture Baseline, that the conflict against the older `AUTH_API.md` wording has been consciously resolved in favor of the newer, more deliberate design — not overlooked.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Implement server-side token revocation for V1, to match the old `AUTH_API.md` wording literally | Requires a blacklist/cache layer that isn't otherwise needed in V1, purely to match a generic spec written before the actual JWT design was completed |
| Change JWT to a long-lived, server-tracked session token to make "logout invalidation" meaningful | Contradicts the deliberate "Short-lived Access Token" decision made in the JWT Design session, and reintroduces server-side session state the Stateless design was specifically chosen to avoid |

---

## Consequences

- ✅ No behavioral change needed to the already-completed JWT and Authentication design — it was correct.
- ✅ `docs.zip`'s `09-api-reference/02-AUTH_API.md` needs a follow-up correction: the `POST /auth/logout` description should read *"Removes the token client-side. No server-side invalidation occurs in V1."*
- 🟡 **Future Version:** Token Revocation and Refresh Tokens are explicitly earmarked as future work, to be introduced only if a real requirement emerges (e.g., "force logout all devices" as an admin action).
