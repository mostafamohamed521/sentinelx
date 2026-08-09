# 05 — Module Dependencies

> Source: Session 5 — Module Dependencies
> Updated for the resolved 8-module set: `Organization` replaces `Company` as the Root Module; `Authentication` (with its Identity and API Key submodules) has two internal dependency directions, detailed in Section 4.

---

## 1. The Right Question

Most people ask *"who is allowed to use which module?"* The correct question is:

> **"Who depends on whom?"**

A significant difference.

### First Rule

> **Dependency means awareness.** If Module A depends on Module B, A knows B exists. The reverse is not required.

---

## 2. The Root of the System

Which entity is the largest in SentinelX? **Organization** — everything else exists inside an Organization. So:

```text
Organization depends on nothing.
Organization is Root.
```

---

## 3. Agent

```text
Agent depends on Organization.       (an Agent knows which Organization it belongs to)
Agent does NOT depend on Authentication. (an Agent doesn't care who created it)
```

```text
Agent
    ↓
Organization
```

---

## 4. Authentication (Both Submodules)

This is the one module with two distinct internal dependency chains, because it serves two different actor types.

### Identity Submodule (Human)
```text
Identity (submodule)
    ↓
Organization
```
A User must belong to an Organization.

### API Key Submodule (Agent Credential)
```text
API Key (submodule)
    ↓
Agent
    ↓
Organization
```
An API Key belongs to an Agent, which belongs to an Organization.

### Important: Agent Does Not Depend on API Key
```text
Agent  ✘→  API Key
```
This is a deliberate, valuable decision: API Keys could be replaced by any other credential mechanism tomorrow without changing anything about the Agent entity itself.

---

## 5. Observation

Where does an Observation come from? An Agent.

```text
Observation
    ↓
Agent
    ↓
Organization
```

But: `Observation` **does not know** about `Analysis`, and does not know about `Alert`. This is one of the most important architectural decisions in the entire backend design.

---

## 6. Analysis

What does Analysis analyze? An Observation.

```text
Analysis
    ↓
Observation
```

Does it depend on Agent directly? **No** — everything it needs is already available inside the Observation it's analyzing. A deliberate, clean decision.

---

## 7. Alert

Where does an Alert come from? Analysis — **not** Observation directly.

```text
Alert
    ↓
Analysis
```

---

## 8. Dashboard

Dashboard reads from everyone, but **nothing depends on Dashboard**.

```text
Dashboard
    ↓
Observation
    ↓
Analysis
    ↓
Alert
    ↓
Agent
```

No module anywhere is aware that Dashboard exists — this keeps Dashboard a pure Consumer.

---

## 9. Audit

Similar to Dashboard, but instead of reading, it **receives events**.

```text
All Modules
    ↓ (emit events to)
Audit
```

No module depends on Audit.

---

## 10. The Complete Picture

```text
Organization
     /        \
    ▼          ▼
Identity      Agent
(Auth submodule)  │
                  ▼
             API Key
          (Auth submodule)
                  │
                  ▼
             Observation
                  │
                  ▼
              Analysis
                  │
                  ▼
                Alert

Dashboard
    │
    ├────────► Observation
    ├────────► Analysis
    ├────────► Alert
    └────────► Agent

Audit
▲
│
└──────────── every module
```

---

## 11. The Golden Law: Dependencies Are One-Way

```text
Observation → Analysis          ✔ allowed
Analysis → Observation → Analysis  ✘ Circular Dependency — forbidden
```

### Practical Example
`Analysis` needs `Observation`. Fine. Does `Observation` need to know the result of `Analysis`? **No.** If that result is ever needed elsewhere, it's fetched via a Repository — never by reaching back into the Analysis module directly. This prevents confusion at the source.

---

## 12. Second Rule: Lower Modules Never Know Higher Modules

```text
Observation does not know Dashboard.
Agent does not know Alert.
Organization does not know ML.
```

This preserves clean layering across the entire dependency graph.

---

## 13. What If a Module Needs Information It Doesn't Own?

Example: `Analysis` needs to know *"is this Agent still Active?"* The answer is: **it asks the Agent module.** It never reads the `agents` table directly.

> **Ownership is more important than database access.**

---

## 14. Third Rule: Dependency Does Not Mean Database Access

A commonly mishandled point. `Analysis depends on Observation` does **not** mean:

```sql
SELECT * FROM observations
```

It means depending on a **Business Contract**. `Observation` says *"here is an Observation."* `Analysis` says *"thank you"* — without knowing any storage detail underneath.

---

## 15. Fourth Rule: Dashboard Is Always Last

```text
Observation → Dashboard → Analysis   ✘ never
```

Dashboard is always at the end of any chain — it's purely a Viewer.

---

## 16. Fifth Rule: Audit Never Controls

Audit only records. If Audit fails, `Login`, `Observation`, and `Alert` must all continue to function regardless. It is a Recorder, nothing more.

---

## 17. A Simple Test: Loose Coupling

```text
Remove Dashboard  → does the system still work?  Yes.
Remove Audit       → does the system still work?  Yes.
Remove Analysis      → does Observation still work? Yes — it receives and stores, just without analysis.
```

This is exactly what **Loose Coupling** means.

---

## 18. The Golden Test

> **"If we replaced the ML Engine entirely, which modules would be affected?"**

Answer: **`Analysis` only.**

> **"If we replaced Authentication entirely?"**

Answer: **`Authentication` only.**

> **"If we replaced Dashboard entirely?"**

Answer: **`Dashboard` only.**

This is the actual goal of Modular Architecture.

---

## 19. Session 5 Summary (Resolved)

```text
Module Dependencies

Root Module
────────────────────
✔ Organization

Authentication (Identity submodule)
────────────────────
Depends on Organization

Authentication (API Key submodule)
────────────────────
Depends on Agent (→ Organization)

Agent
────────────────────
Depends on Organization

Observation
────────────────────
Depends on Agent

Analysis
────────────────────
Depends on Observation

Alert
────────────────────
Depends on Analysis

Dashboard
────────────────────
Reads from:
• Agent
• Observation
• Analysis
• Alert

Audit
────────────────────
Receives events from all modules

────────────────────

Architecture Rules

✔ Dependencies are one-way.
✔ No circular dependencies.
✔ Modules communicate through business contracts.
✔ Ownership is more important than database access.
✔ Dashboard is always the last consumer.
✔ Audit never controls business logic.
✔ Infrastructure changes should affect only one module.
```

---

## 20. The Most Important Decision in This Session

> **Every module depends only on the nearest module it actually needs — never on the system as a whole.**

This single decision is what protects SentinelX from the most common failure mode months into development: a small change that starts breaking unrelated, distant parts of the system. Under this design: changing Authentication's mechanism affects `Authentication` only; changing how API Keys are issued affects the `API Key` submodule only; replacing the ML Engine entirely affects `Analysis` only; changing the Dashboard affects no Business Logic anywhere.

---

## 21. Looking Ahead

Everything so far has looked at the project **horizontally** (Features, Modules, Dependencies). [`06-implementation-layers.md`](./06-implementation-layers.md) turns to look **vertically** — going *inside* each module and asking: what is this module itself built out of?
