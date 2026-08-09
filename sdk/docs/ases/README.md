# SentinelX — ASES Integration Layer Documentation

> **Status:** 🔒 **FROZEN** (V1)
> **Component:** Client-side integration layer connecting AI Agent frameworks to the SentinelX platform
> **Owner:** SDK / Integration Architecture Team

---

## 1. Why This Folder Exists

This is not a Python package tutorial, and it is not a description of how to call `pip install`. It is the **single official Source of Truth** for how AI Agent frameworks get connected to SentinelX: what problem this layer solves, why it is shaped the way it is, and what every internal component is and isn't responsible for.

This documentation is written to serve four audiences at once, and it must satisfy all four simultaneously:

- **A new engineer** joining this layer, who should be productive within hours, not weeks.
- **A technical recruiter or interviewer**, evaluating the engineering maturity behind this project.
- **An external senior engineer or judging panel**, assessing this work against world-class standards.
- **The original author, years later**, who needs to remember *why*, not just *what*.

If any explanation here would fail one of these four readers, it isn't finished.

> **If you are a customer integrating ASES into your own Agent, start at [`sdk/README.md`](../../README.md) instead.** This document set exists for engineering design rationale — the *why* behind every decision — not customer onboarding; a first-time integrator is not one of the four audiences above, deliberately (RC-10, DX-004). `sdk/README.md` and [`sdk/docs/getting-started.md`](../getting-started.md) are the real, customer-scoped entry points, and link back here for any reader who wants the full rationale behind a specific decision.

---

## 2. The One Rule Every Decision in This Folder Traces Back To

> **We do not monitor Agents. We standardize the events a Framework allows us to observe, and transform them into a single, canonical Observation sent to SentinelX.**

This single sentence is the reason this component is called the **ASES Integration Layer**, and not "the SDK." Calling it an SDK invites the assumption that it's an all-seeing monitoring package. It isn't, and it can never be — no Python package can see inside an arbitrary Agent's execution unless that Agent's framework explicitly exposes a hook to see it through. Every architectural boundary in this documentation exists because of this one constraint.

---

## 3. Where This Component Sits in SentinelX

```text
SentinelX Platform
│
├── Backend (Database, Authentication, Backend Architecture)
│
└── ASES Integration Layer   ← you are here
        │
        ├── Framework Adapters (CrewAI, LangGraph, ...)
        ├── Observation Engine (Collector, Builder, Validator)
        ├── Transport Layer (Queue, Worker, Serializer, API Client)
        └── Public API (configure, monitor, shutdown)
```

The Integration Layer is the only part of SentinelX that runs **inside the customer's own process**, alongside their Agent. Every design decision here is shaped by that single fact: this code must never be the reason an Agent fails, slows down, or behaves differently than it would without SentinelX installed.

---

## 4. Folder Architecture

```text
docs/
└── ases-integration-layer/
    │
    ├── README.md                                    ← you are here
    │
    ├── 01-overview.md                                 ← What the Integration Layer is, and is not
    ├── 02-integration-philosophy.md                     ← How we reach into a running Agent
    ├── 03-agent-integration-models.md                     ← Callbacks, Middleware, Decorators, Wrappers
    ├── 04-public-api.md                                     ← The customer-facing surface
    ├── 05-customer-integration-journey.md                    ← The full onboarding story, end to end
    ├── 06-integration-point-concept.md                         ← The precise boundary of what we can observe
    ├── 07-agent-framework-ecosystem.md                           ← Which frameworks, why, and in what order
    ├── 08-internal-architecture.md                                ← The processing pipeline and its components
    ├── 09-observation-lifecycle.md                                  ← When an Observation starts, ends, and lives
    ├── 10-transport-layer.md                                          ← How data leaves the process safely
    ├── 11-repository-architecture.md                                    ← How the codebase itself is organized
    ├── 12-packaging-and-distribution.md                                   ← How customers install and configure it
    ├── 13-implementation-roadmap.md                                        ← The build order, milestone by milestone
    │
    ├── adr/                                             ← The pivotal architectural decisions, and why
    │   ├── ADR-001-adapter-based-framework-strategy.md
    │   ├── ADR-002-thin-adapter-principle.md
    │   ├── ADR-003-in-memory-observation-buffering.md
    │   ├── ADR-004-non-blocking-async-transport.md
    │   ├── ADR-005-sdk-responsibility-boundary.md
    │   ├── ADR-006-domain-driven-repository-structure.md
    │   └── ADR-007-single-package-distribution.md
    │
    ├── contracts/                                       ← Exact, implementation-ready specifications
    │   ├── public-api-contract.md
    │   ├── adapter-signal-contract.md
    │   └── environment-configuration.md
    │
    ├── diagrams/                                         ← SVG diagrams
    │   ├── architecture/
    │   ├── sequence/
    │   ├── state/
    │   └── flow/
    │
    └── glossary.md
```

---

## 5. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What this layer actually does — and the one job it deliberately does not do |
| 2 | [`02-integration-philosophy.md`](./02-integration-philosophy.md) | Why we can never "monitor any Agent," and what we do instead |
| 3 | [`03-agent-integration-models.md`](./03-agent-integration-models.md) | The mechanical options for hooking into a framework, and how we choose between them |
| 4 | [`04-public-api.md`](./04-public-api.md) | The three functions a customer ever needs to call |
| 5 | [`05-customer-integration-journey.md`](./05-customer-integration-journey.md) | The full story, from `pip install` to a result on the Dashboard |
| 6 | [`06-integration-point-concept.md`](./06-integration-point-concept.md) | The precise, honest boundary of what this layer can and cannot see |
| 7 | [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md) | The frameworks we target, in what order, and why some are explicitly out of scope |
| 8 | [`08-internal-architecture.md`](./08-internal-architecture.md) | The six-stage internal pipeline and the naming discipline behind it |
| 9 | [`09-observation-lifecycle.md`](./09-observation-lifecycle.md) | The hardest engineering question in this entire layer: when does an Observation begin and end? |
| 10 | [`10-transport-layer.md`](./10-transport-layer.md) | How an Observation leaves the process without ever risking the host Agent |
| 11 | [`11-repository-architecture.md`](./11-repository-architecture.md) | How the actual codebase is laid out, and why |
| 12 | [`12-packaging-and-distribution.md`](./12-packaging-and-distribution.md) | How this ships, versions, and is documented for the outside world |
| 13 | [`13-implementation-roadmap.md`](./13-implementation-roadmap.md) | The milestone-by-milestone build order |
| — | [`adr/`](./adr) | The six decisions that shaped this layer the most, with full reasoning |
| — | [`contracts/`](./contracts) | Exact signatures and formats — implementation-ready |
| — | [`diagrams/`](./diagrams) | Architecture, sequence, state, and flow diagrams |
| — | [`glossary.md`](./glossary.md) | Every term, defined exactly once |

---

## 6. The Golden Chain

Every session that shaped this layer, and every file in this folder, reduces to one chain that is never violated:

```text
Framework Event
    ↓
Adapter                (sees the Framework — nothing else)
    ↓
Event Pipeline          (routes — builds nothing)
    ↓
Observation Collector    (groups Events, owns the Lifecycle)
    ↓
Observation Builder       (converts to ASES JSON — Stateless)
    ↓
Observation Validator      (enforces the Schema)
    ↓
Transport                   (Queue → Worker → Serializer → API Client)
    ↓
SentinelX REST API
```

No component reaches backward. No component absorbs a neighbor's responsibility. This is the single property this entire documentation set exists to protect.

---

## 7. Design Status

```text
ASES Integration Layer Design
████████████████████████████ 100%

Integration Philosophy          ✅ Frozen
Agent Integration Models         ✅ Frozen
Public API                        ✅ Frozen
Customer Journey                   ✅ Frozen
Integration Point Boundary          ✅ Frozen
Framework Ecosystem Strategy          ✅ Frozen
Internal Architecture                  ✅ Frozen
Observation Lifecycle                    ✅ Frozen
Transport Layer                           ✅ Frozen
Repository Architecture                    ✅ Frozen
Packaging & Distribution                    ✅ Frozen
Implementation Roadmap                       ✅ Frozen
```

---

## 8. What We Deliberately Did NOT Do (V1 Scope)

```text
❌ Monitoring arbitrary/closed-source AI applications (Claude Code, Cursor, ChatGPT Desktop)
❌ A CLI (no real use case exists yet)
❌ Multiple PyPI packages (ases-core, ases-crewai, ...)
❌ Persistent/disk-backed Observation storage
❌ Server-side awareness inside the SDK (it never learns the ML result)
❌ Support for Python versions older than 3.11
❌ AutoGen, Google ADK, Mastra adapters (planned, not V1)
```

Every one of these was discussed and rejected for a specific reason — never out of oversight. Most are explicitly earmarked as **Future Evolution**, including a distinct future product idea (a "Desktop Sensor" for closed-source AI applications) that is deliberately **not** a feature of this layer — see [`06-integration-point-concept.md`](./06-integration-point-concept.md).
