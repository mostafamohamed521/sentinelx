# 06 — Authorization Design

> Source: Session 6 — Authorization Design
> This is, in a real sense, the final piece of the Identity puzzle. After this session, "who are you and how do you prove it" is fully settled, and the next sessions turn to protecting the system itself.

---

## 1. Revisiting the First Decision of Session 1

```text
Authentication
    ↓
Authorization
    ↓
Business Logic
```

We said these two are separate. Now we explain why.

---

## 2. What Does Authorization Actually Mean?

> **Authorization is the process of determining whether an authenticated identity is allowed to perform a specific action.**

Focus on: **Authenticated Identity**. Authorization never runs unless Authentication has already succeeded.

### A Simple Example

Ahmed logged in — Authentication succeeded. But can Ahmed delete an Agent? That's where Authorization begins.

The question shifts from **"Who are you?"** to **"Can you do this?"**

---

## 3. First Architectural Decision

> **Authorization does not know Password, JWT, or API Key.**

It knows exactly one thing: **Authenticated Identity** — the result of everything built in the first five sessions.

---

## 4. What Does an Authorization Decision Depend On?

In many projects the answer is simply `Role`. That's correct, but incomplete. The decision here is based on three things together:

```text
Authenticated Identity
+
Requested Action
+
Resource
```

### Example

```text
Ahmed
    ↓
Delete Agent
    ↓
Agent #15
```

Authorization asks: Who is Ahmed? What is he trying to do? On which Agent, specifically?

---

## 5. Does an Agent Need Authorization?

Many would say no — that's a mistake.

Consider the Agent's flow: it sends an **Observation**. Can it send an Observation on behalf of a different Agent? **No.** Can it generate API Keys? **No.** Can it access the Dashboard? **No.**

So even the Agent has permissions — just different ones from a human's.

### Human Permissions (examples)

```text
View Dashboard
Create Agent
Rotate API Key
View Alerts
Manage Team
Update Organization
```

### Agent Permissions

```text
Submit Observation
```

That's it. The Agent has essentially one permission — and this keeps the **attack surface** extremely small.

---

## 6. Do We Use RBAC?

After considering multiple schools of thought (ABAC, Policy Engines like OPA, etc.), the decision for this project is:

> **RBAC (Role-Based Access Control) is the right choice.**

```text
Role
    ↓
Permissions
    ↓
Actions
```

Simple, clear, and extensible. No ABAC, no Policy Engine, no OPA, no added complexity.

---

## 7. What Are Our Roles?

SentinelX is not an ERP organization — it's a security platform. It doesn't need twenty roles.

For the current project:

```text
Owner
Admin
Member
```

| Role | Meaning |
|------|---------|
| **Owner** | The organization's founder/owner |
| **Admin** | Manages the platform's day-to-day operation |
| **Member** | Views results and works with them |

If more roles are needed later, they can be added — but not now.

---

## 8. Is Role Stored Inside the JWT?

**No** — because Role can change.

Imagine Ahmed was `Admin`, and a minute later becomes `Member`. If the JWT carried the Role, he would remain `Admin` until the token expires — a real security risk.

So: the JWT carries only the `Identity ID`. Afterward, the system fetches the Role from the database on every check. See [`adr/ADR-001-role-storage.md`](./adr/ADR-001-role-storage.md) for the full reasoning.

---

## 9. Where Does Authorization Execute?

Immediately before the Business layer:

```text
Request
    ↓
Authentication
    ↓
Authorization
    ↓
Business Logic
```

Not inside the Controller. Not inside the Service. Not inside the Repository. It's an independent layer.

---

## 10. What Is the Output of Authorization?

Many say `Role`. That's wrong. The real output is:

```text
Allowed
or
Denied
```

Then either continue, or stop.

---

## 11. Is Authorization Responsible for Error Messages?

**No.** It only says `Allowed` or `Denied`. The API layer is responsible for returning `403 Forbidden`. This preserves separation of concerns. See [`contracts/auth-errors.md`](./contracts/auth-errors.md).

---

## 12. Does the Agent Participate in the Role System?

**No** — an elegant point. **Humans** have a `Role`. **Agents** do not have a `Role`.

An Agent has exactly one **Capability**: `Submit Observation`. There is no `Agent Role`, no `Agent Admin`, no `Agent Viewer`. None of that exists — which simplifies the design considerably.

---

## 13. The Complete Flows

### Human Request

```text
Request
    ↓
Authentication
    ↓
Authenticated Identity
    ↓
Load Role
    ↓
Check Permission
    ↓
Allowed
    ↓
Business Logic
```

### Agent Request

```text
Request
    ↓
API Key Verification
    ↓
Authenticated Identity
    ↓
Check Capability
    ↓
Allowed
    ↓
Submit Observation
```

Both begin the same way, but the decision mechanism differs — naturally, since Human nature differs from Agent nature.

---

## 14. Important Architectural Point

Look at the whole chain:

```text
Identity
    ↓
Credential
    ↓
Authentication
    ↓
Authenticated Identity
    ↓
Authorization
    ↓
Business Action
```

This is, in our view, the cleanest flow reached so far in the design, because it separates each responsibility from the ones around it.

---

## 15. Session 6 Summary

```text
Authorization Design

Purpose
Determine whether an authenticated identity can perform a requested action.

────────────────────────

Authorization Input
✔ Authenticated Identity
✔ Requested Action
✔ Target Resource

────────────────────────

Human Authorization
Role
    ↓
Permissions
    ↓
Decision

────────────────────────

Agent Authorization
Capability
    ↓
Decision

────────────────────────

Rules
✔ Authentication comes first.
✔ Authorization never reads Passwords, JWTs, or API Keys.
✔ JWT does not contain Roles.
✔ Roles are loaded from the database.
✔ Authorization returns Allow or Deny only.
✔ Agent does not use Roles.
✔ Agent has a fixed capability to submit observations.
```

---

## 16. The Most Important Decision in This Session

> The decision to separate Humans from Agents at the Authorization level.

Humans are governed by a Roles & Permissions system, because they interact with the Dashboard, settings, and platform management. Agents don't need Roles at all, because their function is extremely narrow: sending Observations with their own API Key.

This matters because it prevents unnecessary complexity from entering the system. Trying to apply the same Role system to Agents would be solving a problem that doesn't exist. And if a future version needs some Agents to have different capabilities, this can be added as a natural evolution without demolishing the current design.

After this session, Identity, Authentication, and Authorization are all architecturally complete, and the next session moves to an entirely different concern: Security Hardening — how everything designed so far stays difficult to attack in a production environment.
