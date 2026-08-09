# 07 — Security Hardening

> Source: Session 7 — Security Hardening
> Every prior session answered *"how does the system work?"* This session answers a completely different question: **"How does the system stay secure even if someone tries to attack it?"**

---

## 1. What Hardening Is NOT

> **Security Hardening does not mean adding new features.**

This session does not introduce new ideas, does not change the architecture, and does not add new components. It simply reviews every decision already made and asks:

> **"What prevents this from being misused?"**

That is the entire meaning of Hardening.

---

## 2. Principle 1 — Trust Nothing

A golden rule. Anything coming from outside the system must be treated as **Untrusted** until proven otherwise:

```text
Email
Password
API Key
Observation JSON
Headers
Query Parameters
```

All of the above are untrusted data.

> **Never trust the client. Always verify.**

---

## 3. Principle 2 — Fail Securely

When an error occurs, what do we do? Many just throw an `Exception` and move on. Instead, we must ask: **could this error open a door for attack?**

For example: instead of returning `API Key abc123 does not exist`, we return simply `Authentication failed`.

**Why?** So we don't help an attacker learn anything about the system's internals.

---

## 4. Principle 3 — Least Privilege

One of the most important security principles. Every Identity gets the **minimum** permissions possible:

```text
Member is not Admin.
Admin is not Owner.
Agent can do nothing except: Submit Observation.
```

This is a decision already made in Session 6.

---

## 5. Principle 4 — Secrets Never Leak

What counts as a Secret in this project?

```text
Passwords
JWT Secret
API Keys
ML Service Secret
Database Credentials
```

The rule:

> **Secrets never appear in Logs, API Responses, or the Dashboard.**

Even when an error occurs — the Secret is never leaked.

---

## 6. Principle 5 — Audit Everything Important

Highly relevant for this project — SentinelX is, after all, a security platform, so it must record important security events.

Not everything — just the important things, for example:

```text
User Login
Failed Login
API Key Created
API Key Rotated
API Key Revoked
Password Changed
Role Changed
Agent Created
Agent Archived
```

**Why?** So that if an incident happens, we know how to trace it back.

---

## 7. Principle 6 — Rate Limiting

Does every endpoint need the same rate? **No.**

`Login`, for instance, must be strictly rate-limited to prevent password guessing. `Submit Observation`, on the other hand, is a completely different endpoint — Agents may legitimately send thousands of Observations.

So: **every endpoint gets its own appropriate policy**, not one universal policy.

---

## 8. Principle 7 — Secure by Default

One of the most valued principles here. Any new feature must be **secure from day one** — we don't wait for someone to remember to secure it later.

For example, when a new Agent is created, its state should be:

```text
Active
    ↓
Ready
```

but if a feature is sensitive, it should not auto-activate without a reason. The idea is that the **default state must be secure.**

---

## 9. Principle 8 — Don't Expose Internal Details

A common mistake. Instead of returning:

```text
Table 'api_keys' not found.
```
or
```text
JWT signature verification failed.
```

We return:

```text
Unauthorized
```
or
```text
Invalid request.
```

The full details go to internal Logs — never to the user. See [`contracts/auth-errors.md`](./contracts/auth-errors.md) for the exact error contract.

---

## 10. Principle 9 — Security Is Layered

The single most important point in this whole session. Security never relies on a single layer. Consider one request:

```text
Request
    ↓
HTTPS
    ↓
Authentication
    ↓
Authorization
    ↓
Validation
    ↓
Business Rules
    ↓
Database Constraints
```

Note: even if one layer fails, another layer is behind it. This is called **Defense in Depth**.

---

## 11. Principle 10 — Security Should Not Pollute the Domain

An architectural point. `Authentication`, `Authorization`, `API Keys`, and `JWT` are all Infrastructure. Business Logic doesn't need to know about any of them.

A Service responsible for creating an Agent should not need to know: *did this request arrive with a JWT? An API Key? OAuth?* — it only knows the **Authenticated Identity**. This is a direct extension of everything designed so far.

---

## 12. The Complete Security Pipeline

```text
Incoming Request
    ↓
HTTPS
    ↓
Authentication
    ↓
Authorization
    ↓
Input Validation
    ↓
Business Logic
    ↓
Database
    ↓
Audit Log
```

Every layer protects the layer after it — this is exactly what is meant by a **Production Mindset**.

---

## 13. Are We Missing Any Security Features?

**No** — an important point. We do not currently need to discuss:

```text
MFA
SSO
OAuth
Device Fingerprinting
Geo Blocking
Risk-based Authentication
Adaptive Authentication
```

Not because they're bad — but because they're out of scope for the MVP. Adopting them now would break a rule established at the very start of the project:

> **Build the right foundation first, then expand.**

---

## 14. Session 7 Summary

```text
Security Hardening

Core Principles
✔ Trust Nothing
✔ Fail Securely
✔ Least Privilege
✔ Secrets Never Leak
✔ Audit Important Actions
✔ Rate Limiting
✔ Secure by Default
✔ Hide Internal Details
✔ Defense in Depth
✔ Keep Security Outside the Domain

────────────────────────

Security Pipeline
Request
    ↓
HTTPS
    ↓
Authentication
    ↓
Authorization
    ↓
Validation
    ↓
Business Logic
    ↓
Database
    ↓
Audit Logging
```

---

## 15. The Most Important Decision in This Session

> **Security is not a feature here — it's a property present in every layer of the system.**

This means we never rely on a single "magic fix" to protect the platform. Instead, every layer carries a clear responsibility:

- **Authentication** proves identity.
- **Authorization** determines permissions.
- **Validation** rejects invalid data.
- **Business Logic** enforces product rules.
- **The Database** preserves data integrity.
- **The Audit Log** records important events for review.

This is, in our view, the real difference between an ordinary portfolio project and a system designed with a Production-Level Architecture mindset.

---

## 16. Where Are We Now?

After seven sessions, the entire intellectual and architectural side of Authentication is complete:

```text
✅ Authentication Philosophy
✅ Identity Design
✅ Authentication Flows
✅ JWT Design
✅ API Key Design
✅ Authorization Design
✅ Security Hardening
⬜ Organization & Identity Lifecycle
⬜ Implementation Roadmap
```

The next session, [`08-identity-lifecycle.md`](./08-identity-lifecycle.md), does not introduce new authentication concepts — but it fills a gap that was noticed only after this session: **how does an Organization, and the people inside it, actually come into existence?**
