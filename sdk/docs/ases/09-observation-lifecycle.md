# 09 — Observation Lifecycle

> Source concept: Session 6. This is, by this project's own assessment, the single hardest engineering question in the entire Integration Layer: when does an Observation begin, when does it end, and how does the SDK know an Agent has finished a Task?

---

## 1. What an Observation Actually Is

Not an Event. Not a Log entry.

> **An Observation is the complete execution story of a single Agent Task.**

If an Agent runs a Task called *"Search latest AI news,"* the Observation is the full journey of executing that Task:

```text
Start Task
    ↓
LLM Call
    ↓
Tool Call
    ↓
HTTP Request
    ↓
File Read
    ↓
Finish
    ↓
Observation
```

An Observation is not a snapshot. **It is a story.**

---

## 2. When Does It Begin?

Three candidate answers were weighed:

### Candidate 1 — The First Event Starts It
Simple, but flawed: if the Agent sends a single Event and then stops, the Observation is left open forever with no way to close it.

### Candidate 2 — The Framework Explicitly Announces "Task Started"
Excellent when available — but not every framework provides this signal, so relying on it wouldn't be generic.

### Candidate 3 — The Adapter Explicitly Announces "Observation Started"
Rejected, because it would make the Adapter smart, violating the Thin Adapter principle established in [`08-internal-architecture.md`](./08-internal-architecture.md).

### The Adopted Answer

> **The Collector begins an Observation at the first Event it receives — but never considers it Complete until it has positive confirmation that it actually ended.**

---

## 3. When Does It End? (The Harder Half of the Question)

Four terminating conditions, in priority order:

```text
1. The Framework explicitly reports "Task Finished."       → send immediately. (Primary, easiest case.)
2. The Agent's execution itself concludes.                    → equally reliable.
3. Timeout — no new Event for 30 seconds.                        → a backup, never the primary signal.
4. SDK shutdown — any still-open Observation is flushed and sent.   → important for graceful exits.
```

This produces the Collector's full state machine:

```text
Started → Collecting → Completed → Building → Sending → Archived
```

See [`diagrams/state/observation-state.svg`](./diagrams/state) for the full visual.

---

## 4. Can Multiple Observations Exist at Once?

**Yes, definitely.** An Agent running asynchronously might have Task A, Task B, and Task C all in flight simultaneously. This means the Collector must be able to tell which Event belongs to which in-flight Observation.

---

## 5. Correlating Events Without Forcing IDs on the Customer

This question resurfaces a decision made earlier in the project's history: `task_id` and `observation_id` were deliberately removed from anything the customer is required to supply (see the ASES JSON Schema documentation for that original decision). So how does correlation happen without asking the customer for an ID?

> **The Collector relies on a Runtime Context that the Adapter is able to provide from the framework itself.**
>
> Named "Runtime Context" throughout this document, matching the `runtime_context` field name `contracts/adapter-signal-contract.md` already uses — not "Execution Context," which collides with the Backend's own, entirely different wire-format `context` section (execution environment metadata, sent on every Observation; see `01-overview.md §4.2`). This was previously a genuine naming collision between two unrelated concepts (`PIPELINE-004`); the rename here is documentation-only and changes no behavior — the field itself was always called `runtime_context`.

For example: CrewAI exposes an Execution object; LangGraph exposes a Graph Run. The Adapter forwards this as an **internal** context — never as part of the outward-facing ASES JSON.

### Two Distinct Levels, Made Explicit for the First Time Here

```text
Internal Runtime Context   →  used only by the SDK itself, for correlation.
ASES Observation             →  the standardized payload sent to the Backend.
```

This separation is treated as a genuinely important decision: internal correlation machinery must never leak into the wire format that SentinelX receives.

---

## 6. Where Does the Collector Store In-Flight Events?

> **In memory. Nowhere else.**

An Observation's entire lifespan is measured in seconds, not hours — so no database, no file, and no Redis is needed to hold it. If the process crashes, the in-flight Observation is lost — and that is accepted as the correct behavior in V1, because a process crash means the Agent itself has crashed; losing the last, incomplete Observation in that scenario is a reasonable, bounded loss, not a defect.

---

## 7. Efficiency: One Build, Not Five Hundred

If an Observation accumulates 500 Events, the Collector gathers all of them, and the Builder constructs the final JSON **exactly once**, at the end — never incrementally, per Event. This has a valuable side effect:

> **The Builder is Stateless.** It runs once, after collection completes, and only the Collector ever tells it to start.

```text
Collector → Builder     (direction only ever flows this way)
```

The Builder knows nothing about JSON transport, nothing about the API — and the Collector, symmetrically, knows nothing about JSON or the API either. It only knows Events. This clean separation is deliberate and load-bearing.

---

## 8. What Happens If an Observation Fails?

```text
An Event is malformed.
    ↓
Collector finalizes the Observation anyway.
    ↓
Builder attempts construction.
    ↓
Validator rejects it.
    ↓
Transport never sees it at all.
```

A clean, predictable failure path with no ambiguity about which component is responsible for catching the problem.

---

## 9. How Does the Collector Actually Know the Framework Finished?

Drawing out the full journey surfaces a subtlety: the Collector cannot know CrewAI's internal completion rules — so who does? The Adapter. But this cannot be allowed to make the Adapter "smart" again, contradicting everything established in [`08-internal-architecture.md`](./08-internal-architecture.md).

### The Resolution: Exactly Two Signal Types, Nothing Else

```text
EVENT
OBSERVATION_COMPLETED
```

Instead of emitting something semantically rich like *"Tool Started,"* the Adapter emits a generic `EVENT`. When it detects the framework has finished, it emits `OBSERVATION_COMPLETED` — and that's the full extent of its intelligence. It never builds an Observation, never touches JSON, and knows nothing about ML. It only ever states:

> **"I observed that execution has ended."**

This is, in this project's own assessment, the best balance found between a **Thin Adapter** and a **Smart Collector** — full detail on the exact signal contract is in [`contracts/adapter-signal-contract.md`](./contracts/adapter-signal-contract.md).

---

## 10. The Full Lifecycle Journey

```text
Framework
    ↓
Adapter
    ↓
Event
    ↓
Collector starts an Observation
    ↓
Collector gathers Events
    ↓
Collector detects the end
    ↓
Builder constructs ASES JSON
    ↓
Validator
    ↓
Transport
    ↓
SentinelX API
```

---

## 11. Summary

```text
Observation Lifecycle

Observation Definition
An Observation represents the complete execution story of a single
Agent task. It is not a single event. It is a collection of related
events.

────────────────────────

Lifecycle
Started → Collecting → Completed → Building → Sending → Archived

────────────────────────

Observation Collector
Responsibilities:
- Start new observations.
- Collect incoming events.
- Track observation state.
- Receive lifecycle signals.
- Trigger the Builder when completed.

────────────────────────

Observation Builder
Responsibilities:
- Convert collected events into an ASES Observation.
- Stateless.
- Runs only after collection is complete.

────────────────────────

Runtime Context
Internal SDK state used only for event correlation.
Never exposed in the ASES JSON Schema.

────────────────────────

Memory Strategy
Observations remain in memory until completed.
Persistent storage is not required in V1.

────────────────────────

Adapter Signals
The Adapter may emit:
- EVENT
- OBSERVATION_COMPLETED
Nothing more.
```

---

## 12. The Decision Behind This Whole Document

The question this entire session set out to resolve was: **which component gets to be "smart"?** The Adapter? The Core? The Builder? The answer landed on a deliberately balanced split:

```text
The Adapter knows only "what happened inside the Framework."
The Collector knows only "how to gather Events."
The Builder knows only "how to turn them into ASES JSON."
```

No single component absorbs another's responsibility. This is exactly the kind of architecture that, two years from now, when a fifth or tenth framework needs support, will not require redesigning the SDK — because every part knows exactly one thing, and does it well. See [`ADR-003-in-memory-observation-buffering.md`](./adr/ADR-003-in-memory-observation-buffering.md) for the full reasoning behind the memory-only storage decision specifically.
