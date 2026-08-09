# ADR-002: Adapters Emit Only Two Signal Types — EVENT and OBSERVATION_COMPLETED

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 5 (Internal SDK Architecture), finalized in Session 6 (Observation Lifecycle) |
| **Affects** | Every Adapter's implementation contract, and the division of intelligence across the whole pipeline |

---

## Context

An Adapter lives inside a specific framework's execution and is the only component with direct visibility into what's happening there. A decision was needed about how much responsibility an Adapter should carry — should it build Observations itself? Know the ASES Schema? Decide when execution has meaningfully concluded?

---

## Decision

**An Adapter does the least possible amount of work**, and communicates exclusively through two signal types:

```text
EVENT                    — "I observed something happen."
OBSERVATION_COMPLETED      — "I observed that execution has ended."
```

It never builds an Observation, never performs an HTTP request, never knows the ASES JSON Schema, and never implements retry logic.

---

## Rationale

### The Microphone Analogy
An Adapter is a microphone: it transmits what it hears; it does not interpret it. Any intelligence beyond "notice and relay" belongs to a downstream component that can apply that intelligence uniformly, regardless of which framework produced the signal.

### Balancing "Thin Adapter" Against a Real Engineering Need
Session 6 surfaced a real tension: something has to know when a framework has finished a Task, and only the Adapter is positioned to know that, framework by framework. The resolution avoids making the Adapter "smart" in a way that would reintroduce framework-specific logic into the pipeline: the Adapter is allowed to say `OBSERVATION_COMPLETED`, but it is not allowed to decide *what a completed Observation looks like*, or build one. That responsibility stays entirely with the Collector and Builder (see [`09-observation-lifecycle.md`](../09-observation-lifecycle.md)).

### Why This Specific Split, and Not Some Other One
Three questions were weighed: should the Adapter be smart, should the Core be smart, or should the Builder be smart? The adopted balance is precise: the Adapter knows only *what happened inside the Framework*; the Collector knows only *how to gather Events*; the Builder knows only *how to turn them into ASES JSON*. No single component absorbs another's job.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Adapters emit semantically rich, framework-specific events (e.g., `tool_started`, `node_executed`) directly to the Core | Leaks framework-specific vocabulary into the framework-agnostic pipeline, defeating the purpose of the Canonical Event Model |
| Adapters build and validate their own Observations | Duplicates the Observation Builder's responsibility per framework, and risks inconsistent JSON shape across different Adapters |
| Adapters decide the Observation Lifecycle boundaries themselves | Makes the Adapter "smart" in exactly the way this principle exists to prevent, and would require re-deciding Lifecycle logic independently for every new framework |

---

## Consequences

- ✅ Every Adapter, regardless of framework, is small — Session 8's roadmap notes a real CrewAI Adapter came in around 200 lines, not 3,000, as direct evidence this principle holds in practice.
- ✅ All framework-specific vocabulary is normalized at the earliest possible point, before anything downstream ever sees it.
- ✅ A new engineer reviewing any Adapter's code has an unambiguous, two-signal contract to check it against.
- ⚠️ Exact signal shapes and payloads must be followed precisely by every Adapter author — see [`contracts/adapter-signal-contract.md`](../contracts/adapter-signal-contract.md) for the implementation-ready specification.
