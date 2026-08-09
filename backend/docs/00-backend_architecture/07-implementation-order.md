# 07 — Implementation Order

> Source: Session 7 — Implementation Order
> Updated for the resolved 8-module set: Stage 1 covers `Organization` + `Authentication` (Identity submodule); Stage 2 covers `Agent` + the `API Key` submodule of `Authentication`.

---

## 1. What "Implementation Order" Actually Means

A common misconception: *"build Module 1, then Module 2, then Module 3."* That's wrong. This isn't about ordering modules — it's about ordering the **capabilities** the system needs, so that each one opens the door to the next.

```text
Foundation
    ↓
Identity
    ↓
Core Business
    ↓
Intelligence
    ↓
Presentation
```

---

## 2. The Golden Rule

> **Never implement a feature that depends on something that doesn't exist yet.**

This single rule governs every Sprint that follows.

---

## 3. Stage 0 — Foundation

Before any feature at all, a foundation must exist. Not features for the user — features for the *team*.

```text
Project Initialization
Configuration
Environment
Docker
Laravel Modules
Coding Standards
CI (later)
Testing Infrastructure
Logging
Exception Handling
```

**Definition of Done for Stage 0:** `php artisan serve` runs, the project builds, the module skeletons exist, the architecture is fixed — but there is no Business Logic yet.

---

## 4. Stage 1 — Identity Foundation

Why first? Because everything after this depends on it.

```text
Organization
    ↓
Authentication (Identity submodule)
    ↓
Authorization
```

Covers:
```text
Organizations
Users
Login
Register
JWT
Roles (Owner, Admin, Member)
```

**Why before Agent?** Someone has to create the Agent — and that someone is a User.

---

## 5. Stage 2 — Agent Foundation

With a User and an Organization now available:

```text
Agent
    ↓
Authentication (API Key submodule)
```

Covers:
```text
Create Agent
Update Agent
Archive Agent
Generate API Key
Rotate Key
Revoke Key
```

**Why API Keys here?** Because Observation ingestion is impossible without them.

---

## 6. Stage 3 — Observation Pipeline

The real beginning of SentinelX's core.

```text
Receive Observation
    ↓
Validate ASES Schema
    ↓
Authenticate API Key
    ↓
Store Observation
    ↓
Update Status
```

**No ML yet — deliberately.** The first thing that must be confirmed is: can the SDK actually send an Observation? Does storage work? Is the JSON Schema correct? This is also the first real integration point with the SDK.

---

## 7. Stage 4 — Analysis Pipeline

Once data ingestion is proven correct, intelligence is added.

```text
Observation
    ↓
ML Client
    ↓
Prediction
    ↓
Evidence
    ↓
Store Analysis
```

The first point of contact with FastAPI. Kept as a separate stage so that if ML isn't ready yet, the rest of the team can keep working — one of the goals from the very start of the project.

---

## 8. Stage 5 — Alert Engine

Once analysis exists:

```text
Prediction
    ↓
Risk Rules
    ↓
Alert
    ↓
Alert Status
```

Note: Alert never needs to know about ML — only about the result of Analysis.

---

## 9. Stage 6 — Dashboard & History

Data now exists; time to display it.

```text
Dashboard
Observation History
Alert History
Search
Filters
Statistics
```

All Read Models.

---

## 10. Stage 7 — Audit & Settings

Last, deliberately — these are enhancements, not Core Product.

```text
Audit Logs
Organization Settings
Profile Settings
```

---

## 11. The Complete Order

```text
Stage 0 — Foundation
    ↓
Stage 1 — Identity (Organization + Authentication)
    ↓
Stage 2 — Agent + API Key
    ↓
Stage 3 — Observation
    ↓
Stage 4 — Analysis
    ↓
Stage 5 — Alert
    ↓
Stage 6 — Dashboard
    ↓
Stage 7 — Audit + Settings
```

---

## 12. Sanity-Testing the Order

```text
Can Dashboard be built before Analysis?    No — no data to show.
Can Analysis be built before Observation?  No — no input.
Can Observation be built before API Key?   No — no authentication.
Can API Key be built before Agent?         No — a key belongs to an Agent.
Can Agent be built before Organization?    No — an Agent belongs to an Organization.
```

The order is a natural consequence of the dependency graph in [`05-module-dependencies.md`](./05-module-dependencies.md) — **not** a manual guess.

---

## 13. Important: Stage ≠ Sprint

```text
Stage = Milestone
Sprint = a period of execution within that Milestone
```

`Foundation` might finish in one Sprint. `Observation` might take two. `Analysis` might take two or three. This keeps the plan flexible without losing the ordering logic.

---

## 14. Definition of Done, Per Stage

Every stage must end in a **usable** state.

```text
End of Stage 3: SDK → POST /observations → Validation → Database, with zero mocks.
End of Stage 4: Observation → ML → Prediction → Database, real end to end.
End of Stage 6: a User opens the Dashboard and sees real data.
```

---

## 15. Full Implementation Order Summary

```text
SentinelX Implementation Order

Stage 0 — Foundation
Stage 1 — Organization, Authentication (Identity), Authorization
Stage 2 — Agent, API Key
Stage 3 — Observation Pipeline, ASES Validation, Observation Storage
Stage 4 — ML Client, Analysis, Prediction, Evidence
Stage 5 — Alert Engine, Alert Lifecycle
Stage 6 — Dashboard, Observation History, Search, Filters
Stage 7 — Audit Logs, Settings
```

---

## 16. An Additional Architectural Rule

> **Every Stage must end with an End-to-End working system, even with reduced capability.**

```text
Stage 3 ends with a real Observation sent from the SDK and stored.
Stage 4 ends with a real Observation analyzed via FastAPI.
Stage 5 ends with a real Alert appearing in the database.
Stage 6 ends with that Alert and Observation visible in the Dashboard.
```

Isolated pieces are never built and wired together at the very end — a complete chain is built, step by step. This is the approach closest to real production development, and it keeps every stage demoable and testable, drastically reducing the risk of discovering integration problems late in the project.
