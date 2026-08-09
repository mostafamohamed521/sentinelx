# 04 — Public API

> Source concept: Session 4. This document defines the entire surface a customer is ever exposed to. Everything else this layer does — building, validating, queuing, retrying, serializing — is invisible by design.

---

## 1. The Governing Truth About SDKs

> **An SDK succeeds or fails because of its Public API, not because of its internal code.**

A customer never sees the Observation Builder, the Transport Layer, or the retry logic. What they see is a single line:

```python
from ases import ???
```

If that line — and the handful that follow it — feel complicated, the SDK has already lost, regardless of how well-engineered the internals are.

---

## 2. First Principle: Simple First

The founding constraint from [`01-overview.md`](./01-overview.md) is repeated here as a hard requirement: the simplest possible integration must be **three lines**. Not an aspiration — a design constraint every API decision is checked against.

**Scope of this claim, stated precisely:** "three lines" describes the `monitor()` fast path (section 6a, below) — an import, an Adapter construction, and one `monitor(adapter)` call. The four-operation class-based form (section 6) is the full-control path for a customer who needs more than one Adapter on a single SDK instance, or who needs `attach()`/`start()`/`stop()` as independent, explicit steps; it was never meant to be the three-line form itself, and this document previously left that unstated.

---

## 3. Designing the Shape of the API

### Who Connects the Adapter — the Customer, or the SDK?

The **customer**, because the SDK has no way of knowing which framework — or whether any framework at all — the customer is using.

### Should the SDK Know the Framework Directly?

No — this was already settled in [`03-agent-integration-models.md`](./03-agent-integration-models.md): the Core never knows CrewAI or LangGraph directly. Only the Adapter does. This directly shapes the API: the customer constructs a framework-specific Adapter and hands it to the SDK — the SDK never constructs framework objects itself.

### Attach, Not Configure-In-Constructor

The API converges on an explicit `attach()` call, because it describes the truth plainly: *"attach this Adapter to this SDK instance."* This reads better than passing the SDK into the Adapter's constructor, and it scales cleanly to multiple Adapters if a customer ever needs more than one.

### Should `start()` Be Automatic?

**No — it must be explicit.** A customer might register several Adapters before beginning, and `start()` should be called exactly once, deliberately, when they're ready.

### Does the SDK Run in Its Own Thread or Process?

**No.** `start()` means *"begin accepting events,"* not *"launch a new process."* The Integration Layer lives inside the customer's own application process — see the Transport Layer's non-blocking design in [`10-transport-layer.md`](./10-transport-layer.md) for how this is kept safe.

---

## 4. Does the Customer Ever Touch an Observation?

**No — and this is one of the most important decisions in this document.** The customer never sees:

```text
Observation
Event Builder
Raw JSON
```

If they're using the Manual API fallback, the only thing they ever construct is a single Event via `emit(...)`. Everything downstream of that is this layer's job, not theirs.

---

## 5. Configuration Shape

Settings live outside the constructor, in a single configuration surface (see [`12-packaging-and-distribution.md`](./12-packaging-and-distribution.md) for the full V1 configuration story — environment variables plus a one-time `configure()` call). For V1, the constructor itself stays intentionally minimal:

```python
ASES(api_key="...")
```

No `ASESConfig` object is introduced in V1 — that would be solving a problem V1 doesn't have yet.

---

## 6. The Full-Control Path — Four Operations, No More

```text
1. Create the SDK        →  ases = ASES(api_key="...")
2. Attach an Adapter       →  ases.attach(adapter)
3. Start                     →  ases.start()
4. Stop                        →  ases.stop()
```

Deliberately absent from the public surface:

```text
✘ build_observation()
✘ send()
✘ serialize()
```

All of that is internal. The exact, implementation-ready contract for these four calls is in [`contracts/public-api-contract.md`](./contracts/public-api-contract.md).

Use this path directly when more than one Adapter needs to be attached to a single SDK instance, or when `attach()`/`start()`/`stop()` need to be independent, explicit steps in your own code. For the common single-Adapter case, section 6a's `monitor()` is the three-line form this document's own founding constraint (section 2) refers to.

---

## 6a. The Fast Path — `monitor()` / `configure()` / `shutdown()`

Thin convenience wrappers over the `ASES` class above (section 6), not a second, competing implementation — see [`contracts/public-api-contract.md`](./contracts/public-api-contract.md#8-monitor--configure--shutdown-the-fast-path) for their full contract. `monitor()` constructs an `ASES` instance, attaches exactly one Adapter, and starts it, in one call:

```python
from ases import monitor
from ases.adapters import CrewAIAdapter

monitor(CrewAIAdapter(crew))
```

Genuinely three lines, satisfying section 2's constraint literally. `configure(api_key=...)` sets process-wide configuration a later `monitor()` or `ASES(...)` call can rely on instead of passing `api_key` directly; `shutdown()` stops whatever instance `monitor()` most recently created. A customer needing more than one Adapter uses the full-control path (section 6) instead — `monitor()` deliberately does not grow multi-Adapter support.

---

## 7. The Adapter's Own Public Contract

An Adapter is public API too — every Adapter, regardless of framework, commits to exactly three behaviors:

```text
1. Begin listening for framework events.
2. Stop listening for framework events.
3. Forward events to the SDK.
```

Nothing more. This minimal, uniform contract is what makes it possible to design the internal Core Architecture (see [`08-internal-architecture.md`](./08-internal-architecture.md)) without coupling it to any specific framework.

---

## 8. The Full Shape

```text
Developer
    ↓
ASES(api_key)
    ↓
attach(Adapter)
    ↓
start()
    ↓
SDK handles everything automatically
    ↓
stop()
```

### Complete Example (Full-Control Path) — CrewAI

```python
from crewai import Crew
from ases import ASES
from ases.adapters import CrewAIAdapter

crew = Crew(...)

ases = ASES(api_key="sk_live_ab12_9f8e7d6c5b4a3928...")
adapter = CrewAIAdapter(crew)
ases.attach(adapter)
ases.start()

crew.kickoff()

ases.stop()
```

### Complete Example (Fast Path) — CrewAI

```python
from ases import monitor
from ases.adapters import CrewAIAdapter

monitor(CrewAIAdapter(crew))

crew.kickoff()
```

### Complete Example (Full-Control Path) — Generic Agent (Manual API)

```python
from ases import ASES
from ases.adapters import GenericAdapter

ases = ASES(api_key="sk_live_ab12_9f8e7d6c5b4a3928...")
adapter = GenericAdapter()
ases.attach(adapter)
ases.start()

adapter.emit(
    event_type="tool_execution",
    payload={"tool": "search", "query": "latest AI security news"}
)

ases.stop()
```

---

## 9. Summary

```text
Public API

Design Principles
✔ Developer Experience First.
✔ Simple by Default.
✔ Core SDK is Framework Agnostic.
✔ Adapters encapsulate Framework-specific logic.

────────────────────────

Public Surface
Full control:  ases = ASES(api_key="..."); ases.attach(adapter); ases.start(); ases.stop()
Fast path:     monitor(adapter); ...; shutdown()

────────────────────────

Responsibilities

ASES
✔ SDK lifecycle.
✔ Adapter registration.
✔ Event processing pipeline.
✔ Transport orchestration.

Adapter
✔ Listen to framework events.
✔ Convert framework callbacks into SDK events.
✔ Forward events to the SDK.

────────────────────────

V1 Scope
Public surface is intentionally minimal.
All Observation creation, validation, serialization, and transport
remain internal.
```

---

## 10. The Decision That Outlives This Document

> **The Public API expresses Intent, not Implementation.**

The customer tells the SDK: attach to this Agent, start monitoring, stop monitoring. *How* the Observation gets built, *when* it gets sent, *how* retries happen, *how* it becomes ASES JSON — none of that is the customer's concern, and none of it appears in the API surface. This is precisely the difference between an SDK people enjoy using, and one that demands twenty pages of documentation before a single line of code gets written.
