# ADR-003: Observations Are Buffered in Memory Only — No Persistent Storage in V1

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 6 — Observation Lifecycle |
| **Affects** | The Observation Collector's storage strategy, and the SDK's failure-mode behavior on crash |

---

## Context

While an Agent Task is executing, its in-flight Events need to be held somewhere by the Observation Collector until the Observation is complete and ready to be built. A decision was needed about where — and how durably — that in-flight state should be stored.

---

## Decision

**In-flight Observations are held entirely in memory.** No database, no file-based persistence, and no external cache (e.g., Redis) is used in V1. If the host process crashes, any in-flight Observation is lost.

---

## Rationale

### Observations Are Short-Lived by Nature
An Observation's entire lifespan is measured in seconds — the duration of a single Agent Task — not hours or days. Durable storage exists to protect data across restarts or long time horizons; neither applies here.

### A Process Crash Already Means the Agent Crashed
If the SDK's host process terminates unexpectedly, the Agent it was monitoring has, by definition, also stopped running. Losing the single most recent, incomplete Observation in that exact scenario is a small, well-understood, and acceptable loss — not a gap that undermines the platform's value.

### Avoiding Unjustified Infrastructure
Introducing a database, file system dependency, or Redis purely to protect a few seconds of in-flight state would be a clear instance of over-engineering, contradicting this project's standing "Production-ready, without over-engineering" principle.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Persist in-flight Events to a local file or embedded database | Adds real implementation and dependency complexity to protect against a failure window measured in seconds, with no corresponding customer requirement |
| Use an external cache (Redis) for in-flight state | Introduces an external service dependency inside what is meant to be a lightweight, in-process library — directly against the Transport Layer's own "no external dependencies" principle (see [`10-transport-layer.md`](../10-transport-layer.md)) |
| Persist completed-but-unsent Observations to disk before handing off to Transport | Considered and rejected together with Transport's own no-disk-persistence decision — see [`ADR-004-non-blocking-async-transport.md`](./ADR-004-non-blocking-async-transport.md) — for the same reasoning: an Agent process crash already represents Agent failure |

---

## Consequences

- ✅ No new infrastructure dependency is introduced for the sake of Observation buffering.
- ✅ The Collector's implementation stays simple: a plain in-memory data structure, not a persistence layer.
- ✅ Consistent with the Transport Layer's own decision not to persist the delivery Queue to disk in V1.
- ⚠️ A process crash during Task execution results in the loss of that specific in-flight Observation — this is a known, accepted V1 limitation, not an oversight, and should be stated plainly in customer-facing documentation rather than left implicit.
