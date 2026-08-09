# 02 — Product Scope & Features

> Source: Session 2 — Product Scope & Features
> Updated for Documentation Baseline v2.0: `Organization` replaces `Company` throughout (see [`adr/ADR-001-organization-naming.md`](./adr/ADR-001-organization-naming.md)), and Team Management / Invitations are marked deferred (see [`adr/ADR-002-human-identity-baseline-update.md`](./adr/ADR-002-human-identity-baseline-update.md)).

---

## 1. What Is SentinelX? (The Elevator Pitch)

> **SentinelX is an AI Security Monitoring Platform that receives AI Agent observations, analyzes them using Machine Learning, detects security threats, stores analysis history, and provides security visibility through alerts and dashboards.**

Every feature in this inventory must trace back to this one sentence.

---

## 2. The First Rule: A Feature Must Deliver User Value

Every feature must answer: **"What does the user get out of this?"** If that question can't be answered, it isn't a Feature.

### Definition of Feature

> **A Feature is real value the user receives, and which can be used independently.**

Not an Endpoint. Not a Table. Not a Model.

```text
"Login"          → Feature
"POST /login"     → not a Feature, just an API

"Manage Agents"  → Feature
"Agent Model"     → not a Feature, just a data structure
```

---

## 3. Method: Discover, Don't Invent

> **No New Features. Only Discover Existing Features.**

Every feature listed below must already have a basis in prior design work (Product Story, Business Flow, ASES Specification, ML Contract, Authentication Design) — nothing here is a new idea introduced at this stage.

---

## 4. The Customer Journey

Instead of starting from Modules, the full journey through the product is walked first:

```text
Register Organization
    ↓
Create Account
    ↓
Login
    ↓
Create Agent
    ↓
Generate API Key
    ↓
Integrate SDK
    ↓
Receive Observations
    ↓
Analyze Observations
    ↓
Detect Threats
    ↓
View Results
    ↓
Review History
    ↓
Receive Alerts
    ↓
Manage Settings
```

This is a usage journey, not a module list — the modules will be derived from it in [`03-system-modules.md`](./03-system-modules.md), not assumed ahead of time.

---

## 5. Feature Groups

### Group 1 — Identity & Access
Everything related to logging in and managing identity.

```text
Authentication
Organization Management
User Profile
```

> **Team Management is deferred.** See the scope note in Section 6.

### Group 2 — Agent Management
```text
Create Agent
Update Agent
Archive Agent
View Agent Details
List Agents
```
Framed around user value, not raw CRUD.

### Group 3 — API Key Management
Independent from the Agent entity itself, because the client interacts with it as its own concern.
```text
Generate API Key
Rotate API Key
Revoke API Key
View API Key Metadata
```

### Group 4 — Observation Ingestion
The heart of SentinelX.
```text
Receive Observation
Validate Observation
Store Observation
Track Processing Status
```
The first feature group belonging to the Core Product.

### Group 5 — Security Analysis
Also Core.
```text
Send Observation to ML
Receive Analysis
Store Prediction
Attach Evidence
```
Note: this is about the *value the platform provides*, not about the ML model internals themselves.

### Group 6 — Alert Management
```text
Generate Alert
View Alerts
Filter Alerts
Resolve Alert
```
The MVP is limited to viewing and managing status.

### Group 7 — Observation History
```text
Browse History
View Observation Details
Search Observations
Filter Observations
```
Part of the product story from day one.

### Group 8 — Dashboard
Not a page — a Feature.
```text
Security Overview
Recent Observations
Recent Alerts
Risk Distribution
System Health
```
No new widgets are being invented here — this is purely a translation of decisions already made.

### Group 9 — Audit Logs
Specific to the platform itself.
```text
View Security Events
View Administrative Actions
```
Distinct from Observation History: Audit tracks users and administration, not Agents.

### Group 10 — Workspace Settings
```text
Organization Settings
Profile Settings
```
No expansion beyond this for now.

---

## 6. Scope Note: Team Management and Invitations (Resolved)

The original Feature Inventory included a full `Team Management` group (inviting members, accepting invitations, role assignment). Following the Cross-Review resolution in [`adr/ADR-002-human-identity-baseline-update.md`](./adr/ADR-002-human-identity-baseline-update.md):

- ❌ **`Team Management` as a distinct feature group is excluded from V1.**
- 🟡 **`Invitations` (the mechanism for a second user joining an Organization) is deferred to a future version.**
- ✅ **Authentication, Organization Management, User Profile, and a minimal RBAC (`Owner`, `Admin`, `Member`) remain in V1**, so the role model is ready the moment invitations are introduced.

**Practical consequence for V1:** an Organization is provisioned with exactly one User — its Owner — created at registration time. There is no in-product flow for that Owner to add teammates yet. The Role enum exists and is enforced everywhere it's checked, but only ever has one populated member (`Owner`) until Invitations ship in a later version.

---

## 7. The Final Feature Inventory

```text
SentinelX Features

Identity & Access
──────────────────────
✔ Authentication
✔ Organization Management
✔ User Profile

Agent Management
──────────────────────
✔ Manage Agents

API Key Management
──────────────────────
✔ Generate API Keys
✔ Rotate API Keys
✔ Revoke API Keys

Observation Pipeline
──────────────────────
✔ Receive Observation
✔ Validate Observation
✔ Store Observation
✔ Track Processing

Security Analysis
──────────────────────
✔ ML Analysis
✔ Prediction Storage
✔ Evidence Storage

Alert Management
──────────────────────
✔ Alert Generation
✔ Alert Listing
✔ Alert Resolution

Observation History
──────────────────────
✔ Browse History
✔ Search
✔ Filters
✔ Details

Dashboard
──────────────────────
✔ Security Overview
✔ Recent Activity
✔ Risk Summary

Audit Logs
──────────────────────
✔ Administrative Events

Workspace
──────────────────────
✔ Organization Settings
✔ User Profile

Deferred to Future Versions
──────────────────────
🟡 Team Management
🟡 Invitations
```

Note: no mention of Laravel, Controllers, APIs, or Database anywhere above — this is Product Design, not Implementation. Module boundaries emerge from this inventory in the next session, not the other way around.

---

## 8. The Most Important Decision in This Session

From this point on, every Endpoint, Service, Model, or Test must answer one question:

> **"Which Feature am I serving?"**

If it doesn't have a clear entry in this inventory, it is most likely one of:
- Excess code.
- A new idea outside the MVP.
- A design that needs reconsideration.

This keeps SentinelX focused on its core purpose without bloat or over-engineering, while remaining a real, extensible product for future versions.
