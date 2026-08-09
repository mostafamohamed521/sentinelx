# 08 — Sprint Roadmap

> Source: Session 8 — Sprint Roadmap
> Updated for the resolved 8-module set: `Organization` replaces `Company`; Sprint 1/2 reflect the consolidated `Authentication` module (Identity + API Key submodules).

---

## 1. Definition of Sprint

A Sprint here is **not** a fixed time box. It is:

> **A body of work that ends in a testable increment of value.**

A Sprint cannot end with "we wrote some Models." It must end with something that actually works.

### The Golden Rule

Every Sprint must satisfy three conditions:

```text
Build
    ↓
Test
    ↓
Demo
```

After every Sprint: the system runs, it can be tested, and it can be demoed to the team.

---

## 2. Sprint 0 — Project Foundation

The only goal: make the project ready for development.

```text
Laravel Project
    ↓
Module Structure
    ↓
Docker
    ↓
Environment
    ↓
Coding Standards
    ↓
Testing Setup
    ↓
Exception Handling
    ↓
Logging
    ↓
GitHub Structure
```

**Deliverable:** `SentinelX Skeleton` — the project runs, but there are no Features yet.

---

## 3. Sprint 1 — Identity Foundation

The first real value delivered to a user.

```text
Organization
    ↓
Users
    ↓
Authentication
    ↓
Authorization
    ↓
JWT
```

**Definition of Done:** `Register → Login → Get Profile → Logout` all work end to end.

---

## 4. Sprint 2 — Agent Management

```text
Agent CRUD
    ↓
Agent Status
    ↓
Agent Metadata
    ↓
Generate API Key
    ↓
Rotate
    ↓
Revoke
```

**Definition of Done:** `User → Create Agent → Generate API Key`.

---

## 5. Sprint 3 — Observation Pipeline

The first Sprint inside the Core Product.

```text
SDK
    ↓
POST Observation
    ↓
ASES Validation
    ↓
Database
```

Includes: Observation API, JSON Validation, Repository, Storage.

**Definition of Done:** the SDK sends a real Observation successfully.

---

## 6. Sprint 4 — ML Integration

```text
Observation
    ↓
FastAPI
    ↓
Prediction
    ↓
Evidence
    ↓
Database
```

**Definition of Done:** the first real Prediction exists.

---

## 7. Sprint 5 — Alert Engine

```text
Prediction
    ↓
Risk Score
    ↓
Alert
    ↓
Resolve
```

**Definition of Done:** the first real Alert appears.

---

## 8. Sprint 6 — Dashboard

```text
Overview
    ↓
Observation History
    ↓
Alert History
    ↓
Search
    ↓
Filters
```

**Definition of Done:** a User sees real data on the Dashboard.

---

## 9. Sprint 7 — Audit + Settings

```text
Audit
    ↓
Organization Settings
    ↓
Profile
    ↓
Security Logs
```

**Definition of Done:** the system is ready as a complete MVP.

---

## 10. The Full Sprint Map

```text
Sprint 0 — Foundation
    ↓
Sprint 1 — Identity
    ↓
Sprint 2 — Agents
    ↓
Sprint 3 — Observation
    ↓
Sprint 4 — Analysis
    ↓
Sprint 5 — Alerts
    ↓
Sprint 6 — Dashboard
    ↓
Sprint 7 — Audit & Settings
```

---

## 11. Working System: Epic → Feature → Task → Commit

A single Sprint is too coarse a unit to plan real work against. The full breakdown used by the team is:

```text
Epic
    ↓
Feature
    ↓
Task
    ↓
Commit
```

### Example

```text
Epic: Authentication
    ↓
Feature: User Login
    ↓
Tasks:
    Create Login Endpoint
    Validate Credentials
    Generate JWT
    Return User Resource
    Write Tests
    ↓
Commits:
    feat(auth): add login endpoint
    feat(auth): implement jwt generation
    test(auth): add login feature tests
```

### A Second Example

```text
Epic: Observation Pipeline
    ↓
Features: Receive Observation, Validate ASES, Store Observation
    ↓
Tasks: Controller → Action → Repository → Tests
```

This structure means project-tracking tools (Jira, GitHub Projects) end up mirroring the design almost exactly.

---

## 12. The Official Team Workflow

```text
Roadmap
    ↓
Sprint
    ↓
Epic
    ↓
Feature
    ↓
Task
    ↓
Branch
    ↓
Commit
    ↓
Pull Request
    ↓
Review
    ↓
Merge
```

No step here is arbitrary.

---

## 13. Tying Work Directly Back to the Architecture

Every Task must be traceable to exactly three things:

```text
Task
    ↓
Module
    ↓
Layer
    ↓
Feature
```

### Example

```text
Task: ReceiveObservationAction
    ↓
Module: Observation
Layer: Application
Feature: Receive Observation
```

No task is ever left "floating," disconnected from the architecture.

---

## 14. Definition of Done for the Entire MVP

SentinelX's MVP is considered complete when this full scenario runs with **zero mocks**:

```text
Organization Registration
    ↓
User Login
    ↓
Create Agent
    ↓
Generate API Key
    ↓
SDK sends Observation (ASES)
    ↓
Observation Validation
    ↓
Store Observation
    ↓
Send to ML
    ↓
Receive Prediction
    ↓
Generate Alert
    ↓
Dashboard displays Result
    ↓
User reviews Observation History
```

If this scenario works end to end, SentinelX MVP exists.

---

## 15. Final Roadmap

```text
SentinelX Roadmap

Epic 0 — Foundation
Epic 1 — Identity
Epic 2 — Agent Management
Epic 3 — Observation Pipeline
Epic 4 — Analysis Engine
Epic 5 — Alert Engine
Epic 6 — Dashboard
Epic 7 — Audit & Workspace
```

---

## 16. The Full Journey, In Review

```text
Product Vision
    ↓
User Story
    ↓
Business Flow
    ↓
ASES Specification
    ↓
ML Contract
    ↓
Authentication Design
    ↓
Backend Architecture
    ↓
Implementation Roadmap
```

Every stage built on the one before it — no decision was made in isolation from the rest of the design. This is precisely what avoids having to redesign large parts of the system mid-implementation.

---

## 17. The Most Important Decision in This Entire Series

The decision to **separate planning from implementation entirely**. The temptation to "just start writing code" was deliberately resisted in favor of building: a clear product vision, clear module boundaries, stable contracts between components, a logical build order, and a Roadmap directly convertible into Issues, Branches, and Commits.

This means the very first line of code written for SentinelX will have a known home, a known responsibility, and a clear reason for existing.

---

## 18. Before Implementation Actually Begins

One final, small step remains before the first line of code is written: an **Implementation Documentation** guide — a constitution covering the final folder structure, module names, layer names, naming conventions, the rules for creating a new Feature, branch/commit conventions, testing conventions, and Pull Request review rules. This ensures every team member (and Claude Code) works with the exact same conventions from day one.
