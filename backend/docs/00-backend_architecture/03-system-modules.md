# 03 — System Modules

> Source: Session 3 — System Modules
> **Resolved per [`adr/ADR-003-module-consolidation.md`](./adr/ADR-003-module-consolidation.md):** the original 9-module design (with `Identity` and `API Key` as standalone modules) is consolidated into **8 modules**, with Identity and API Keys becoming internal submodules of `Authentication`. `Company` is renamed `Organization` throughout, per [`adr/ADR-001-organization-naming.md`](./adr/ADR-001-organization-naming.md).

---

## 1. What Is a Module?

A common misunderstanding is that a Module is just a folder. It is not.

> **A Module is a part of the system that owns a clear Business Responsibility, and contains everything it needs to fulfill it.**

Focus on **Business Responsibility**, not Technical Responsibility.

`Authentication` is not a module because it happens to use JWT — it's a module because it is responsible for **proving identity**. `Observation` is not a module because it has a database table — it's a module because it is responsible for **receiving and managing Observations**.

### First Rule

```text
Module = Business Capability
```

Every module answers exactly one question: *"What am I responsible for?"* — never *"How many controllers do I have?"*

---

## 2. From Features to Modules

Section 5 of [`02-product-scope-features.md`](./02-product-scope-features.md) produced ten feature groups. That does not mean ten modules — related groups that revolve around the same business concept are merged.

### Authentication Module (consolidated)

Owns everything related to proving identity and issuing credentials — for **both** Humans and Agents:

```text
Authentication
├── Identity (submodule)   — Users, Login, JWT, Roles
└── API Key (submodule)    — Generate, Rotate, Revoke, Validate
```

**Why consolidated instead of split?** Per [`adr/ADR-003-module-consolidation.md`](./adr/ADR-003-module-consolidation.md): both submodules exist to answer the same higher-level business question — *"is this request coming from who it claims to be?"* — just for two different actor types. Keeping them under one module boundary reduces the total module count without blurring their very different internal ownership (Identity data vs. Credential data — see [`04-module-responsibilities.md`](./04-module-responsibilities.md)).

### Organization Module (renamed from Company)

```text
Organization
Workspace
Organization Settings
```

**Why independent?** The Organization is the Root Entity in SentinelX — almost everything else is connected to it.

### Agent Module
```text
Agents
Agent Lifecycle
Agent Status
```

### Observation Module
The heart of the system.
```text
Receive Observation
Validate
Store
Retrieve
```
Note: **not** responsible for analysis.

### Analysis Module
Everything that happens *after* an Observation is stored.
```text
Send to ML
Receive Prediction
Store Analysis
Evidence
```
Kept separate from Observation because analysis logic may evolve — but ingestion must remain stable.

### Alert Module
```text
Generate Alerts
Manage Alerts
Resolve Alerts
```
Deals with the *results* of analysis, never with ML directly.

### Dashboard Module
Read-only.
```text
Statistics
Widgets
Charts
Overview
```
Owns no business logic — it's an Aggregation Layer.

### Audit Module
Specific to the platform's own operation.
```text
Audit Events
Security Events
```
Distinct from Observation History.

---

## 3. Is There a Settings Module?

**No** — an explicit decision. `Settings` is not a Business Capability; it's an extension of `Organization`. Instead of a new module, `Organization` owns it directly.

---

## 4. Final Module Count: 8

```text
Authentication
Organization
Agent
Observation
Analysis
Alert
Dashboard
Audit
```

**Why not more?** To avoid fragmentation. **Why not fewer?** To avoid a God Module.

---

## 5. Module Relationships (High-Level)

```text
Organization
│
├──────────────┐
│              │
Authentication  Agent
│              │
│         (API Key submodule)
│              │
└───────► Observation
                │
                ▼
           Analysis
                │
                ▼
             Alert
                │
                ▼
           Dashboard

Audit
└── receives events from every module
```

Full detail, including exact one-way dependency direction, is in [`05-module-dependencies.md`](./05-module-dependencies.md).

---

## 6. The Most Important Architectural Rule

> **Modules do not communicate with each other's database tables.**

The `Observation` module never reads the `Alerts` table. The `Alert` module never reads `API Keys`. `Authentication` never edits `Agents`. Every module owns its own data — this rule is the foundation for [`05-module-dependencies.md`](./05-module-dependencies.md).

---

## 7. Does Dashboard Read From Every Module?

**Yes** — but there's an important distinction. Dashboard owns no data of its own. It is purely a **Read Model** that aggregates and displays data owned elsewhere.

---

## 8. What SentinelX Looks Like After This Split

```text
SentinelX

├── Authentication
│      ├── Identity (submodule)
│      └── API Key (submodule)
│
├── Organization
│
├── Agent
│
├── Observation
│
├── Analysis
│
├── Alert
│
├── Dashboard
│
└── Audit
```

Note what's deliberately **absent**:

```text
✖ Utils Module
✖ Common Module
✖ Helpers Module
✖ Shared Module
```

These tend to turn into a "junk drawer" over time. If shared code is genuinely needed, it's addressed at the implementation-layer level (see [`06-implementation-layers.md`](./06-implementation-layers.md)), never as a Business Module of its own.

---

## 9. A Simple Test

Ask any new team member:

> "If you wanted to change the Alert logic, where would you go?"

The answer must be immediate and obvious: `Alert Module`.

> "Want to change how an Observation is stored?"

`Observation Module`.

If the answer is ever unclear, the module split is wrong. This is a simple but genuinely used test in real design reviews.

---

## 10. Session 3 Summary (Resolved)

```text
SentinelX Modules — Baseline v2.0

Authentication
────────────────────────
✔ Identity submodule (Users, Login, JWT, Roles)
✔ API Key submodule (Generate, Rotate, Revoke, Validate)

Organization
────────────────────────
✔ Workspace
✔ Organization Settings

Agent
────────────────────────
✔ Agent Lifecycle

Observation
────────────────────────
✔ Receive
✔ Validate
✔ Store

Analysis
────────────────────────
✔ ML Communication
✔ Prediction
✔ Evidence

Alert
────────────────────────
✔ Alert Lifecycle

Dashboard
────────────────────────
✔ Read Models
✔ Statistics
✔ Overview

Audit
────────────────────────
✔ Security Events
✔ Administrative Events

────────────────────────

Architecture Rules

✔ Module = Business Capability
✔ Every Module owns one responsibility.
✔ Modules own their own data.
✔ Dashboard is read-only.
✔ No God Modules.
✔ No Utility Modules.
```

---

## 11. The Most Important Decision in This Session

The separation between `Observation` and `Analysis` remains the single most consequential decision here — `Observation`'s responsibility ends the moment a record is validated and persisted; `Analysis` begins only after that. This gives each module a single reason to change, and it means replacing the ML Engine, adding additional models, or making analysis asynchronous can all happen without touching the Observation pipeline at all.
