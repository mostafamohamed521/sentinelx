# 01 — Architecture Baseline Review

> Source: Session 1 — Architecture Baseline Review
> This file establishes the ground rule for everything that follows: design is finished, implementation is beginning, and drift between the two is the single most common reason software projects fail.

---

## 1. The Mindset for This Entire Series

This phase is not "let's split the project into modules." It is approached the way a Software Architect would approach their first week at a company, being told by the CTO: *"Forget the code — I want to know exactly how this product gets built, from the first folder to production."*

---

## 2. Architecture Is Now Frozen

> **Architecture is now Frozen.**

```text
❌ No new features.
❌ No changes to the JSON.
❌ No changes to the ML Contract.
❌ No changes to Authentication.
❌ No changes to the Domain.
```

Everything agreed upon so far becomes the **Baseline**. Any new idea from this point forward is filed under **Future Versions** — it does not touch the MVP. This single rule prevents the majority of implementation-time confusion.

---

## 3. The Architecture Review Method

Before designing anything new, the entire existing documentation set is re-read — not casually, but as a formal **Architecture Review**, specifically checking for:

```text
Has any decision changed?
Is anything contradictory?
Has any flow been modified?
Has any feature been dropped?
Has anything been forgotten?
Has anything been duplicated?
Is naming inconsistent?
Is the Domain still stable?
```

---

## 4. The Output: A Single Baseline Document

The result of this review is exactly one document — arguably the most important document in the entire project — containing:

```text
1. Product Scope
    ↓
2. Domain Model
    ↓
3. Features List
    ↓
4. System Modules
    ↓
5. Module Responsibilities
    ↓
6. Module Dependencies
    ↓
7. Implementation Order
    ↓
8. Sprint Plan
```

From this moment on, every new decision must be checked against this Baseline first.

---

## 5. The Roadmap for This Series

```text
Backend Architecture Design

Session 1 — Architecture Baseline Review
    ↓
Session 2 — Product Scope & Features
    ↓
Session 3 — System Modules
    ↓
Session 4 — Module Responsibilities
    ↓
Session 5 — Module Dependencies
    ↓
Session 6 — Implementation Layers
    ↓
Session 7 — Implementation Order
    ↓
Session 8 — Sprint Roadmap
```

Note the deliberate ordering: the series does **not** begin with Modules. Naming a module `Authentication` before knowing how many features the product actually has is a common mistake — module boundaries are a *result* of understanding the product, not an assumption made in advance.

---

## 6. Naming: SentinelX vs. ASES

An important clarification that applies to every document in this and the Authentication series:

```text
SentinelX  = the Product / Platform name.
ASES       = the internal Specification name only (Agent Security Event Specification).
```

```text
SentinelX Platform
├── ASES Specification
├── ML Engine
├── Dashboard
├── REST API
├── SDK
└── Alerting System
```

This mirrors how Docker has a Dockerfile, Kubernetes has a YAML Specification, and OpenTelemetry has the OTLP Protocol — the Product has a name, and its wire-level Specification has a separate name. From this point on, all documentation lives under the `SentinelX` name; `ASES` is referenced only when discussing the Event Specification or the JSON Schema specifically.

---

## 7. The Golden Rule for the Whole Series

```text
No New Ideas.
No New Features.
No Architecture Changes.
Only Implement What Was Designed.
```

This rule is repeated throughout the documentation set because it will be referenced constantly during implementation. Most projects don't get lost because of bad code — they get lost because the architecture keeps changing *while* the code is being written. From this point forward, the design phase is considered closed, and the phase of translating design into implementation has begun.
