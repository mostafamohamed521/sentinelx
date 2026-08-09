# 04 — Module Responsibilities

> Source: Session 4 — Module Responsibilities
> Updated for the resolved 8-module set: `Authentication` now owns both the Identity and API Key submodules; `Organization` replaces `Company`.

---

## 1. The Department Analogy

Think of each Module as a Department inside a company. Every department has a manager, staff, and clear work — but it is never allowed to do another department's job. That's exactly the standard applied here.

### The Golden Rule

Every module must be able to answer:

> **"What do I own?"**

Never:

> "What do I use?"

---

## 2. Authentication Module

### Core Responsibility
Proving the identity of both Human and Agent actors, and issuing/validating their credentials. This module has two internal submodules with **different data ownership and different upstream dependencies** — see [`05-module-dependencies.md`](./05-module-dependencies.md) — but they share one module boundary because both exist to answer the same question: *"is this really who it claims to be?"*

### Owns (Identity submodule)
```text
Users
Sessions
Passwords
JWT Identity
Roles (Owner, Admin, Member)
```

### Owns (API Key submodule)
```text
API Keys
Key Hashes
Rotation History
```

### Responsible For
```text
Register User
Login User
Logout User
Verify Password
Reset Password
Email Verification
Current User (/me)
Generate API Key
Rotate API Key
Revoke API Key
Verify API Key
```

### Does NOT Own
```text
Organizations
Agents (as an entity)
Observations
Alerts
```

---

## 3. Organization Module

### Core Responsibility
Owns the Workspace in its entirety — the Root Entity of the whole platform.

### Owns
```text
Organization
Organization Profile
Organization Settings
```

### Responsible For
```text
Create Organization
Update Organization details
Manage Organization settings
Identify the Organization's Owner
```

### Does NOT Own
```text
Login
API Keys
Agents
```

---

## 4. Agent Module

One of the most important modules in the project.

### Owns
```text
Agent
Agent Status
Agent Metadata
```

### Responsible For
```text
Create Agent
Update Agent
Archive Agent (Soft Delete)
View Agent
```

### Does NOT Own
```text
API Keys
Observations
ML
Alerts
```

A commonly mishandled boundary — the Agent module never owns credential or analysis data.

---

## 5. Observation Module

The most important module in SentinelX.

### Owns
```text
Observation
ASES JSON
Observation Status
Processing State
```

### Responsible For
```text
Receive Observation
Validation
Store Observation
Return Observation Details
Search
Pagination
```

### Responsibility Ends At
```text
Stored Successfully
```
After that, its role is complete.

### Does NOT Know About
```text
ML
Risk Score
Alerts
```
The single most important point in this session.

---

## 6. Analysis Module

Begins exactly where Observation ends.

### Owns
```text
Prediction
Evidence
ML Response
Analysis Status
```

### Responsible For
```text
Send Request to ML
Receive Response
Store Result
Store Evidence
Update Analysis Status
```

### Does NOT Own
```text
Observation (only references it)
```

---

## 7. Alert Module

The simplest module.

### Owns
```text
Alert
Alert Status
Resolution
```

### Responsible For
```text
Create Alert
Update Status
Resolve
Reopen
Listing
```

### Does NOT
```text
Perform any analysis.
Communicate with ML.
```

---

## 8. Dashboard Module

Different from every other module — it owns no data. This is a rule, not an oversight.

### Aggregates (does not own)
```text
Observation Summary
Alert Summary
Agent Summary
Risk Summary
```

### Classification
```text
Read Layer
```

---

## 9. Audit Module

Specific to the platform itself.

### Owns
```text
Audit Events
```

### Responsible For
```text
Recording events
Searching events
Displaying events
```

### Never
```text
Intervenes in any business logic.
```

---

## 10. Who Writes to What?

```text
Authentication  → Users, API Keys
Organization     → Organizations
Agent             → Agents
Observation        → Observations
Analysis             → Predictions, Evidence
Alert                 → Alerts
Audit                  → Audit Logs
```

Every module has crystal-clear ownership.

---

## 11. Ownership Is More Important Than Access

Example: `Analysis` needs to *read* `Observation`. Fine. But `Observation` remains the **owner**. This means:

> `Analysis` never modifies `Observation` — except through a clearly defined interface.

This is one of the most important rules of a Modular Monolith.

---

## 12. Who Is Allowed to Create an Alert?

The intuitive-but-wrong answer is `Dashboard` or `Observation`. The correct answer:

```text
Analysis
    ↓
Alert
```

Because an Alert is a *result of analysis*, not a result of receiving an Observation.

---

## 13. The First Two Module Flows

```text
Observation → Analysis → Alert → Dashboard
```

```text
Organization → Authentication → Agent → (API Key submodule) → Observation
```

Every flow here carries real business meaning.

---

## 14. A Module Never Decides on Behalf of Another Module

Example: `Observation` never says *"this is malicious"* — that's not its job. `Analysis` says that. Likewise, `Alert` never computes a Risk Score — `Analysis` does. This prevents duplicated logic across the system.

---

## 15. Final Ownership Map

```text
Authentication
│
├── Owns Users
└── Owns API Keys

Organization
│
└── Owns Organizations

Agent
│
└── Owns Agents

Observation
│
└── Owns Observations

Analysis
│
├── Owns Predictions
└── Owns Evidence

Alert
│
└── Owns Alerts

Dashboard
│
└── Owns Read Models

Audit
│
└── Owns Audit Events
```

---

## 16. Session 4 Summary (Resolved)

```text
Module Responsibilities

Authentication
────────────────────
✔ Users
✔ API Keys
✔ Passwords
✔ Sessions
✔ Roles

Organization
────────────────────
✔ Organization
✔ Workspace
✔ Settings

Agent
────────────────────
✔ Agent Lifecycle

Observation
────────────────────
✔ Receive
✔ Validate
✔ Store
✔ Search

Analysis
────────────────────
✔ ML Communication
✔ Prediction
✔ Evidence

Alert
────────────────────
✔ Alert Lifecycle

Dashboard
────────────────────
✔ Read Models
✔ Statistics

Audit
────────────────────
✔ Audit Events

────────────────────

Architecture Rules

✔ Every Module owns its own data.
✔ Every Module has one business responsibility.
✔ Modules do not duplicate business logic.
✔ Dashboard owns no business logic.
✔ Observation ends at persistence.
✔ Analysis starts after persistence.
✔ Alert depends on Analysis, not Observation.
```

---

## 17. The Most Important Decision in This Session

Adopting **Ownership** as the guiding concept — not "can this module technically reach this data," but "who is the true owner of this data and logic." When a team member asks *"where do I change this logic?"*, the answer is immediate, because they know exactly who the owner is. This is what prevents the God Services and Tight Coupling that most projects only discover months into implementation — see [`05-module-dependencies.md`](./05-module-dependencies.md) for how ownership translates into enforceable dependency direction.
