# 03 — Authentication Flow

> Source: Session 3 — Authentication Flows
> Session 2 answered: *Who can authenticate?* This file answers: **How does authentication actually happen?**

---

## 1. The Rule Behind This Session

> **We are not designing a login page. We are designing the Identity Journey.**

This is a fundamentally different framing than "build a login screen."

---

## 2. Do We Have One Flow?

**No** — the first major decision of this session. We have three Identity types, so we have three flows:

```text
Authentication
├── Human Flow
├── Agent Flow
└── Service Flow
```

But in the MVP, only two are actually implemented:

```text
✔ Human
✔ Agent
✖ Service (Internal) — deferred, consistent with the project's overall philosophy
```

---

## 3. Human Authentication Flow

Walking through the story: Ahmed opens the site for the first time and registers. Can he log in immediately afterward?

**No.** He must first **Verify Email**, because SentinelX is a B2B platform and we do not want fake or unverified emails on the platform.

> **Resolved via [`adr/ADR-005-email-verified-at-column.md`](./adr/ADR-005-email-verified-at-column.md):** `users.status` (`ACTIVE`/`DISABLED`) stays exactly two-valued — `DISABLED` means only "administratively deactivated," never "unverified." Verification state lives on a separate, additive, nullable `users.email_verified_at` timestamp: `NULL` until verified, set once to the verification time when the signed link is followed. A user is `ACTIVE` immediately at registration, but Login is rejected while `email_verified_at IS NULL`.
>
> The mechanism for verifying the link's authenticity (a signed, expiring URL carrying the User's ID, checked by signature and expiry) is exactly as documented below; `email_verified_at` is only where its result is persisted.

### The Journey

```text
Register
    ↓
Email Verification
    ↓
Login
    ↓
Receive Access Token
    ↓
Access Dashboard
    ↓
Logout
```

Note: **Login is not the first step.**

### Is Register Part of Authentication?

**No** — and this is an important distinction. Register is **Identity Creation**, not Authentication.

Authentication only begins here:

```text
Login
    ↓
Verify Password
    ↓
Issue Token
```

---

## 4. Agent Authentication Flow

Entirely different. The Agent never opens a Dashboard and never types a password.

### The Journey

```text
Create Agent
    ↓
Generate API Key
    ↓
Configure SDK
    ↓
Send Observation
    ↓
Backend verifies API Key
    ↓
Accept Request
```

Note: **there is no login at all.** Every single request re-authenticates from scratch. This is simply the nature of API Keys.

### A Useful Comparison

```text
Human:  Login once   → Token → Many Requests
Agent:  Every Request → API Key Verification
```

This is the core structural difference between the two.

### Does the Agent Have a Session?

**No** — and this must be stated explicitly. **The Agent is stateless.** Every request stands alone.

Humans, by contrast, have a logical session represented by the JWT.

---

## 5. Does Authentication Create Identity?

**No.**

- The Human Identity was created before Login (at Register time).
- The Agent Identity was created before the API Key was ever issued.

**Authentication does not create. Authentication verifies.**

---

## 6. What Happens When Authentication Fails?

There is exactly one rule:

```text
Authentication Failed
    ↓
Stop Request Immediately
```

That means: no Business Logic, no Validation, no Database queries, no ML — nothing continues.

---

## 7. Does Authentication Know the Organization?

**Indirectly.**

```text
Human  → Organization is known via the User.
Agent  → Organization is known via the API Key.
```

But the flow itself does not know business details — it only extracts the Identity.

---

## 8. What Is the Output of Authentication?

This question deserves its own ADR-level treatment (see [`04-jwt.md`](./04-jwt.md) and [`adr/ADR-003-jwt-claims.md`](./adr/ADR-003-jwt-claims.md)). People commonly answer `JWT` or `User`.

The real output is:

> **Authenticated Identity**

After Authentication succeeds, the Backend holds exactly one object, for example:

```text
Identity
    ↓
Type: Human
ID: xxx
Organization: xxx
Status: Active
```

or:

```text
Identity
    ↓
Type: Agent
ID: xxx
Organization: xxx
Status: Active
```

Every layer downstream operates on this **Identity object** — never on the raw JWT, the password, or the API Key.

---

## 9. The Complete Flows

### Human

```text
Request
    ↓
Login
    ↓
Verify Password
    ↓
Issue JWT
    ↓
Next Request
    ↓
Verify JWT
    ↓
Authenticated Identity
    ↓
Authorization
    ↓
Business Logic
```

### Agent

```text
Request
    ↓
API Key
    ↓
Verify API Key
    ↓
Authenticated Identity
    ↓
Authorization
    ↓
Business Logic
```

Both flows eventually converge into the exact same final shape — this convergence is deliberate and important.

---

## 10. Important Architectural Point

We have two entirely different kinds of credentials, but after authentication, both are transformed into the exact same thing: an **Authenticated Identity**.

This means the rest of the system never has to know whether it was a JWT, an API Key, or a Secret behind a given request — all it knows is that there is a **verified Identity**. This is what drastically reduces coupling across the system.

---

## 11. Session 3 Summary

```text
Authentication Flows

Human Flow
Register
    ↓
Verify Email
    ↓
Login
    ↓
Issue JWT
    ↓
Authenticated Identity

────────────────────────

Agent Flow
Create Agent
    ↓
Generate API Key
    ↓
SDK Request
    ↓
Verify API Key
    ↓
Authenticated Identity

────────────────────────

Rules
✔ Register is not Authentication.
✔ Authentication never creates identities.
✔ Failed Authentication stops the request immediately.
✔ Human uses sessions (JWT).
✔ Agent is stateless.
✔ Authentication output is always an Authenticated Identity.
```

---

## 12. The Most Important Decision in This Session

> **All authentication mechanisms (Password, JWT, API Key) are merely different means of reaching the exact same result: an Authenticated Identity.**

This decision keeps the design significantly cleaner, because from Authorization all the way to the last service in the system, everyone works with exactly one concept: **Authenticated Identity** — never a Token, an API Key, or a Password directly. This is also exactly what allows large systems to add new authentication methods in the future (like OAuth or SSO) without touching any business logic at all, since every method ultimately converges to the same internal contract.
