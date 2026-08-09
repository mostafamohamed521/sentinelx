# 02 — Domain: Identity Design

> Source: Session 2 — Identity Design
> This file answers: **"Identity of whom, exactly?"** — before any discussion of login, password, or JWT.

---

## 1. Why This Session Comes Before Any Login Discussion

A very common mistake is starting with `JWT`, or `Login`, or `Password`. All three of these answer the question "how do I prove identity?" — but nobody asked the prior question first: **"whose identity, exactly?"** This session answers that prior question.

---

## 2. Definition of Identity

> **Identity is anything that can independently interact with the system.**

Any entity capable of sending a request to the system on its own behalf has an Identity.

---

## 3. Who Can Talk to the Backend?

This question was already answered in Session 1:

```text
Human
Agent
Internal Service
```

These are our Identities. Not `Admin`, `Manager`, `Viewer` — those are Roles, covered separately in [`06-authorization.md`](./06-authorization.md).

---

## 4. First Principle: Identity ≠ User

This is one of the most commonly confused points.

**User** is one *kind* of Identity. But not every Identity is a User.

```text
Ahmed          → Identity
CrewAI Agent   → Identity
ML Service     → Identity
```

So we have **three independent types**.

---

## 5. The Three Identity Types

### 5.1 Human Identity
The person who opens the Dashboard. Owns:

```text
Email
Password
Profile
Role
Organization
```

Note: the email exists here **not** because this identity is "a user" but because email is simply the appropriate credential for humans.

### 5.2 Agent Identity
The most interesting part of the project. An Agent, from the system's point of view, is not a mere script — it is a **full client** with a full identity. It owns:

```text
Name
Organization
Status
API Key
SDK
```

But it has **no password** and **no login screen**. This matters a great deal — we never force the Agent to behave like a human.

### 5.3 Internal Service Identity
The part people often forget. The Backend itself, when it talks to the ML service, needs to answer the question: "who told ML this is really our Backend?" So it too has an Identity — but this is an **internal** identity, not a client-facing one.

---

## 6. First Decision

```text
Identity
├── Human
├── Agent
└── Service
```

---

## 7. Does Every Identity Share the Same Lifecycle?

**No.**

```text
Human
Register → Verify → Login → Use Platform → Logout

Agent
Create → Generate API Key → Send Observations → Rotate Key → Deactivate

Service
Deploy → Configure Secret → Communicate
```

Each one lives an entirely different life. Trying to unify them would be a mistake.

---

## 8. Does Every Identity Have a Credential?

**Yes** — but a different kind for each:

```text
Human    → Password
Agent    → API Key
Service  → Shared Secret
```

This means the Credential is **an attribute of Identity**, not Identity itself.

---

## 9. Does Identity Have State?

**Yes** — and it belongs to Identity, not to Authentication.

```text
Human
Active → Disabled

Agent
Active → Archived
```

> **Reconciliation note (aligned with `01-database/schema/enums.md`):** `users.status` and `agents.status` are each a deliberately minimal two-value enum — `ACTIVE`/`DISABLED` for `users`, `ACTIVE`/`ARCHIVED` for `agents` — and neither carries a third "pending" or "suspended" value.
> - **Human**: `DISABLED` retains its original, sole meaning — an administratively deactivated account, the replacement for deletion. It is **not** used to represent "registered but not yet email-verified" — that is tracked independently via the additive `users.email_verified_at` column, see [`adr/ADR-005-email-verified-at-column.md`](./adr/ADR-005-email-verified-at-column.md) and [`03-authentication-flow.md`](./03-authentication-flow.md#3-human-authentication-flow).
> - **Agent**: `DISABLED` does not exist as a separate value — the one real "stop this Agent" action is `ARCHIVED`, exactly as [`01-database/schema/enums.md`](../01-database/schema/enums.md#4-agentstatus--table-agentsstatus) already decided (Archive *is* the Business Action, not a lesser state before it).

---

## 10. Is Identity Ever Deleted?

We committed to this rule from the very start of the project:

> **Security Data Never Truly Disappears.**

So the answer is **no**. Instead of `Delete`, we do `Archived` or `Disabled`, depending on the identity type.

---

## 11. Does Identity Know the Organization?

In our project — **yes**, in most cases:

```text
Human    → belongs to a Organization
Agent    → belongs to a Organization
Service  → does not
```

This is exactly what keeps the Multi-Tenant Architecture intact.

---

## 12. Final Shape

```text
Identity
          ┌─────────┼─────────┐
          │         │         │
      Human      Agent     Service
          │         │         │
    Password    API Key   Secret
          │         │         │
      Organization   Organization   Internal
```

---

## 13. The Most Important Architectural Decision in This Session

> **Identity is the core entity. Credential is merely a means of proving that identity.**

We are not designing the system around:
```text
Password
JWT
API Key
```

We are designing it around:
```text
Human Identity
Agent Identity
Service Identity
```

And each Identity type then picks the Credential suited to it. This single decision is what keeps the entire authentication system tidy and natural, and it prevents the trap of trying to force every kind of client through the exact same entry mechanism.

---

## 14. Session 2 Summary

```text
Identity Design

Identity
    ↓
Any entity capable of independently interacting with the platform.

────────────────────────

Identity Types
✔ Human Identity
✔ Agent Identity
✔ Service Identity

────────────────────────

Identity Owns
✔ Lifecycle
✔ State
✔ Organization Association
✔ Credential

────────────────────────

Credential Types
Human   → Password
Agent   → API Key
Service → Secret

────────────────────────

Rules
✔ Identity is not a User.
✔ Every Identity has its own lifecycle.
✔ Every Identity has its own credential.
✔ Identity owns state.
✔ Security identities are archived, not deleted.
```

---

## 15. A Naming Convention That Simplifies Everything Downstream

From this point on, whenever we ask **"who is making this request?"**, we never say `User`, `Agent`, or `Service` individually as the answer type. We say **`Identity`**, and then determine its concrete type. This means every piece of middleware, every guard, and every authorization check downstream speaks the exact same language — which drastically reduces implementation complexity.
