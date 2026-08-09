# ADR-001: Role Is Loaded From the Database on Every Request, Never Stored in the JWT

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 6 — Authorization Design |
| **Affects** | JWT contents, the Authorization layer, and every Human-facing endpoint |

---

## Context

The JWT already carries an Identity ID and Identity Type (see [`04-jwt.md`](../04-jwt.md)). A natural next question is whether the user's `Role` (`Owner`, `Admin`, `Member`) should also be embedded inside the JWT, to avoid a database lookup on every request.

---

## Decision

**Role is never stored inside the JWT.** It is fetched from the database on every Authorization check.

```text
JWT contains → Identity ID, Identity Type, Organization ID, Issued At, Expires At
JWT does NOT contain → Role
```

The flow is:

```text
Authenticated Identity (from JWT)
    ↓
Load Role (from database)
    ↓
Check Permission
    ↓
Allow / Deny
```

---

## Rationale

### The Core Problem: Role Can Change Mid-Session
Imagine Ahmed is `Admin`. A minute later, the Owner demotes him to `Member`. If the JWT carried `role: "ADMIN"`, Ahmed would continue to act as an Admin for the entire remaining lifetime of his token — a real, exploitable security gap.

### JWT Is Stateless By Design
The JWT's entire value proposition (see [`04-jwt.md`](../04-jwt.md)) is that it's a minimal, short-lived proof of identity — not a cache of mutable business state. Role is exactly the kind of mutable business data the JWT was explicitly designed to exclude (see [`ADR-003-jwt-claims.md`](./ADR-003-jwt-claims.md)).

### Consistency With the Broader Philosophy
This decision is a direct instance of the general rule established in Session 4: **the JWT carries Identifiers, not Business Data.** Role is business data — it belongs to the Organization & Identity Lifecycle (see [`08-identity-lifecycle.md`](../08-identity-lifecycle.md)), not to the authentication token.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Embed `role` in the JWT payload | Creates a window where a demoted or promoted user's actual permissions lag behind reality until the token expires — unacceptable for a security platform |
| Embed `role` but re-issue a new JWT immediately on every role change | Requires token invalidation/blacklisting infrastructure, which was deliberately deferred (see [`07-security.md`](../07-security.md)) — adds complexity disproportionate to the MVP's needs |

---

## Consequences

- ✅ A role change takes effect immediately, on the user's very next request — no stale permissions window.
- ✅ Keeps the JWT minimal and purely identity-focused, consistent with [`ADR-003-jwt-claims.md`](./ADR-003-jwt-claims.md).
- ✅ Authorization stays cleanly separated from Authentication — it reads current state, not cached claims.
- ⚠️ Adds one database read (or a cache lookup) per Authorization check — an accepted tradeoff, since correctness of access control outweighs this minor cost at the current project scale.
