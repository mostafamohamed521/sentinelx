# 13 — Implementation Roadmap

> Source concept: the ASES Implementation Blueprint — the final session, deliberately framed not as an architect's session but as an Engineering Team Lead's: given three developers and an empty repository, what's the very first thing they should build?

---

## 1. Foundation First, Features Second

> **The first goal is not to write Features. It's to build a Foundation.**

The analogy used to frame this: on day one of constructing a building, nobody installs windows or paints walls — day one is for pouring the foundation. The same principle governs this rollout.

---

## 2. Phase 1 — SDK Skeleton

In the very first week, there should be **no** Observation logic, no API calls, no ML, and not even a working CrewAI integration yet. What should exist is a properly organized, empty repository:

```text
ases/
tests/
examples/
docs/
pyproject.toml
README.md
```

**Why start here?** From day one, the repository should already look and feel like a Production repository — never a scratch space that gets tidied up later.

Immediately afterward, the package structure from [`11-repository-architecture.md`](./11-repository-architecture.md) is created — empty, with no implementation yet:

```text
ases/
    adapters/
    observation/
    transport/
    pipeline/
    config/
    shared/
```

This forces every domain boundary to be agreed upon **before** any code fills it in.

---

## 3. Phase 2 — Configuration

The very first class actually implemented is **not** the Adapter, the Collector, or the Builder — it's `Settings`, because every other part of the project depends on it. It reads `.env`, performs validation, and its job ends there.

---

## 4. Phase 3 — Logger

Immediately next: a proper `Logger`. No class anywhere in this codebase should ever fall back to a bare `print()` — structured logging exists from day one, not bolted on afterward.

---

## 5. Phase 4 — Models

A step many projects defer — deliberately **not** deferred here. The data Models for `Observation`, `Event`, `Metadata`, `Header`, and `Payload` are built first, with **no logic attached** — pure data shapes only.

---

## 6. Phase 5 — Builder

Now the `Builder` gets built — **without any Adapter yet**. A test feeds it raw Events directly and checks whether it produces correct JSON. This is the project's **first genuinely real Feature**.

---

## 7. Phase 6 — Validator

Comes right after the Builder, and is comparatively simple, because the Schema it validates against is already fully defined.

---

## 8. Phase 7 — Transport

Now the `Queue`, then the `Serializer`, then the `API Client` — each with its own dedicated unit tests, matching the decomposition from [`10-transport-layer.md`](./10-transport-layer.md).

---

## 9. Phase 8 — CrewAI Adapter

> **The first Adapter arrives deliberately late — and that lateness is intentional.**

By this point, the Core is already complete, which means the Adapter itself ends up being roughly 200 lines of code, not 3,000 — direct, concrete proof that the earlier architectural investment was worth it.

---

## 10. Phase 9 — First Demo

Only now: a real CrewAI Agent, running ASES, sending a real Observation, visible inside SentinelX. This moment is treated as version **v0.1**.

---

## 11. Phase 10 — LangGraph Adapter

Built only **after** CrewAI succeeds, never before — because its success is what proves the architecture is genuinely framework-agnostic, not just designed to look that way on paper.

---

## 12. Phase 11 — Testing

> **Every Feature is preceded by a test, not followed by one.**

```text
Builder
    ↓
Tests
    ↓
Implementation
```

Never the reverse order.

---

## 13. Phase 12 — Documentation

Every completed Feature gets an accompanying Example immediately — not at the very end of the project. This is what prevents **Documentation Debt** from accumulating silently until release.

---

## 14. Why This Roadmap Is Organized by Milestone, Not by Week

An earlier draft of this plan was organized into calendar weeks. That draft was explicitly discarded:

> **Software projects don't actually move in weeks. They move in Milestones.**

Some phases are small; others are large — a week-based plan hides that reality and creates false pressure. A Milestone-based plan doesn't.

---

## 15. The Six Milestones

### Milestone 1 — Foundation
**Goal:** the repository is ready.
Repository, Package Structure, Configuration, Logger, Shared Models.
**Deliverable:** the project compiles successfully.

### Milestone 2 — Observation Engine
**Goal:** the Builder and Validator work.
Event Models, Observation Builder, Validator.
**Deliverable:** an ASES Observation is generated correctly from sample Events.

### Milestone 3 — Transport Engine
**Goal:** an Observation reaches SentinelX.
Queue, Worker, Serializer, API Client.
**Deliverable:** an Observation reaches a mock SentinelX API.

### Milestone 4 — CrewAI Integration
**Goal:** the first real Agent is monitored.
CrewAI Adapter.
**Deliverable:** a real CrewAI Agent is monitored by ASES.

### Milestone 5 — End-to-End Demo
**Goal:** the complete pipeline works, together.
```text
Agent → ASES SDK → SentinelX API → Database → ML → Dashboard
```
**Deliverable:** the complete monitoring pipeline is working, observably, end to end.

### Milestone 6 — Public Release
**Goal:** ASES ships.
Documentation, Examples, PyPI, Version 1.0.0.
**Deliverable:** a public, production-ready SDK.

---

## 16. The Single Most Important Rule in This Document

> **Build in Vertical Slices, not horizontal Feature batches.**

Rather than fully finishing the Builder, then fully finishing Transport, then fully finishing the Adapter, one at a time — build one thin, complete, runnable slice first:

```text
CrewAI Event
    │
    ▼
Collector
    │
    ▼
Builder
    │
    ▼
Transport
    │
    ▼
Mock API
```

Even if it only supports a single Event type at first. Once this first slice runs end to end, a genuinely working system already exists — and every subsequent Sprint simply adds breadth (more Event types, more frameworks) to something that already works, rather than assembling isolated parts and hoping they fit together at the very end. This materially reduces the risk of discovering an architectural problem a month in, when it would be far more expensive to fix.

---

## 17. Summary

```text
ASES Implementation Blueprint

Development Strategy
Build the SDK from the inside out.

────────────────────────

Milestone 1 — Foundation
Repository, Package Structure, Configuration, Logger, Shared Models.
Deliverable: project compiles successfully.

Milestone 2 — Observation Engine
Event Models, Observation Builder, Validator.
Deliverable: ASES Observation generated from sample events.

Milestone 3 — Transport Engine
Queue, Worker, Serializer, API Client.
Deliverable: Observation reaches a mock SentinelX API.

Milestone 4 — Framework Integration
CrewAI Adapter.
Deliverable: real CrewAI agent monitored by ASES.

Milestone 5 — End-to-End
Agent → ASES SDK → SentinelX API → Database → ML → Dashboard
Deliverable: complete monitoring pipeline working.

Milestone 6 — Release
Documentation, Examples, PyPI, Version 1.0.0.
Deliverable: public production-ready SDK.

────────────────────────

Engineering Rules
- Build by Milestones.
- Deliver working slices.
- Every feature has tests.
- Documentation evolves with implementation.
- Avoid premature optimization.
```

---

## 18. What Comes After This Document

The recommendation this design phase closes on is not to move straight into writing code, but to first convert the full body of design work — Database, REST API, ML Contract, ASES Schema, this Integration Layer, and the broader Sentinel Architecture — into a set of stable, referenceable engineering specifications, so implementation becomes a matter of *translating agreed specifications*, rather than re-litigating decisions mid-implementation. This documentation set is a direct product of that recommendation.
