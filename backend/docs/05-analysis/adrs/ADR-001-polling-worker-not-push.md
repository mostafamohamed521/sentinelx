# ADR-001: Analysis Discovers Work Via a Polling Worker, Never Via a Push From Observation

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Analysis Module, Sprint 4 |
| **Affects** | How `PENDING` Observations are discovered and claimed for processing |

---

## Context

Once an Observation is persisted with `analysis_status = PENDING`, something must eventually notice it and start analysis. Two shapes were available: **Observation module pushes a notification/job the moment it inserts a row**, or **Analysis module independently polls for work**. [`01-database/schema/indexes.md`](../../01-database/01-database/schema/indexes.md) already reserves an index specifically described as serving *"the Worker — the single most important query in the entire backend,"* strongly signaling a polling design was intended from the database layer up. [`05-module-dependencies.md`](../../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §5 and [`docs/backend/observation/05-cross-module-boundaries.md`](../../04-observation/05-cross-module-boundaries.md) already establish that Observation must never call out to Analysis.

---

## Decision

Analysis discovers work by polling (`SELECT ... WHERE analysis_status = 'PENDING' ORDER BY received_at ASC`), via a scheduled Poller that claims a batch and dispatches Queue jobs. The Observation module's `ReceiveObservationAction` never dispatches anything, fires no event Analysis listens for, and has no awareness that a Poller exists.

---

## Rationale

### Why not have Observation dispatch a job the moment it inserts a row — wouldn't that be lower latency?
Because doing so requires `ReceiveObservationAction` to reference a job class, queue name, or event listener that conceptually belongs to Analysis — even a generic `event(new ObservationReceived($id))` still commits Observation to the idea that *something* downstream cares about this moment, and Observation is explicitly documented (per [`docs/backend/observation/01-overview.md`](../../04-observation/01-overview.md) §4) as having zero knowledge that Analysis exists. A poll-based design keeps that boundary perfectly clean: Observation's only obligation is a correctly-indexed, discoverable `PENDING` status — how, whether, or how quickly anything acts on it is entirely Analysis's concern.

### Why does the reserved index already suggest this was the intended design?
[`01-database/schema/indexes.md`](../../01-database/01-database/schema/indexes.md) was written during database design (before this module's own documentation existed) and already frames this exact query as belonging to "the Worker" — a polling process, not an event listener. Building anything else here would mean either leaving that index unused (wasteful, and a strong signal something was designed incorrectly) or building a redundant second discovery mechanism alongside it.

### Isn't polling less "real-time" than a push?
Marginally, yes — a Poller running every few seconds introduces up to that interval's worth of latency before an Observation is even claimed. But per [`ADR-006-Backend-as-Orchestrator`](../../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md), the entire pipeline from Observation to Prediction is already asynchronous by design (`202 Accepted`, per [`docs/backend/observation/adr/ADR-001-async-ingestion-202-accepted.md`](../../04-observation/adr/ADR-001-async-ingestion-202-accepted.md)) — no client is blocking on this latency, and a few seconds of polling interval is immaterial against the ML Engine's own analysis time.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Observation dispatches a Job/Event directly after insert | Violates the frozen "Observation has zero knowledge of Analysis" rule — couples Observation's release cycle to Analysis's existence |
| Database trigger (Postgres `LISTEN`/`NOTIFY`) that Analysis subscribes to | Technically avoids application-level coupling, but introduces database-level coupling (a trigger owned by which module?) and operational complexity (a long-lived listener process) not justified at current scale; no frozen document specifies this mechanism |
| Analysis polls, but at the HTTP layer (a Dashboard-triggered "check for work" endpoint) | Confuses a background processing concern with a public API concern; no such endpoint exists in the frozen API contract |

---

## Consequences

- ✅ The "Observation has zero knowledge of Analysis" boundary, already established in Stage 3, is never even tested — there's no code path where it could accidentally be violated.
- ✅ Reuses the exact index already reserved for this purpose at the database design stage — no wasted infrastructure.
- ✅ Analysis's own availability or slowness never affects Observation's response time (`POST /observations` remains a fast, synchronous write regardless of how backed-up analysis is).
- ⚠️ Introduces polling-interval latency and a small window of "claimed but not yet started" state — acceptable given the platform's already-async design, but worth monitoring (per [`MONITORING_STRATEGY.md`](../../docs/docs/10-operational-architecture/05-MONITORING_STRATEGY.md)'s "Queue Depth" and "Queue Processing Time" metrics) so a growing backlog is visible operationally.
