# 01 — Observation Module Overview

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §5. Nothing here contradicts them.

---

## 1. What Is an Observation, In One Sentence?

> **An Observation is a formal, immutable security document — one completed execution performed by an AI Agent, expressed in the canonical ASES format.**

Per [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §5: *"An Observation is not a log — it's a formal security document."* This single sentence is why `raw_ases_json` is stored exactly as received, byte-for-byte, and never decomposed into normalized tables.

---

## 2. What the Observation Module Is Responsible For

```text
Receive an Observation submission (from an authenticated Agent)
Validate its structural shape against the ASES JSON Schema
Persist it, unmodified, with analysis_status = PENDING
Return it (single + paginated list), scoped to the caller's Organization
```

## 3. What the Observation Module Is Explicitly NOT Responsible For

```text
✘ Deciding whether an Observation is safe/suspicious/malicious   → Analysis module
✘ Computing a risk score                                          → Analysis module
✘ Talking to the ML Engine (FastAPI)                                → Analysis module
✘ Generating Alerts                                                  → Alert module (via Analysis)
✘ Advancing analysis_status past PENDING                              → Analysis module
✘ Validating the *meaning* of Events (was this a real attack?)         → ML Engine, never the Backend
```

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §5, this is called out explicitly as *"the single most important point in this session."* Getting this module's scope right here is worth more than getting any of its individual endpoints right.

---

## 4. The One Rule This Module Never Breaks

> **The Observation module has zero knowledge that ML, Analysis, Prediction, or Alert exist.**

It receives a payload, confirms it's shaped correctly and comes from a real, active Agent, and writes a row. That's the entire job. If a future engineer's instinct is *"while I'm in here persisting the Observation, let me also kick off the ML call"* — that instinct is exactly the mistake [`ADR-006-Backend-as-Orchestrator`](../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md) and [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §5 warn against: *"Does it depend on Agent directly? No — everything it needs is already available inside the Observation it's analyzing"* is written from Analysis's point of view precisely because Observation must stay ignorant of what happens to it next.

---

## 5. Why "Ends At Persistence" Is a Feature, Not a Limitation

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §17 and [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §17 ("A Simple Test: Loose Coupling"):

```text
Remove Analysis entirely → does Observation still work?
    Yes — it receives and stores, just without analysis.
```

This is the exact reason [`07-implementation-order.md`](../00-backend_architecture/00-backend_architecture/07-implementation-order.md) builds Observation in Stage 3 and Analysis in Stage 4, as two genuinely independent increments — Stage 3's Definition of Done is real and demoable (`SDK → POST /observations → Validation → Database`) with **zero** dependency on ML being ready.

---

## 6. Route Ownership vs. Module Ownership (Important — Same Pattern as Stage 2)

Exactly the same nuance documented in [`docs/backend/agent/01-overview.md`](../03-agent/01-overview.md) §5 applies again here, because two of the frozen [`OBSERVATIONS_API.md`](../docs/docs/09-api-reference/04-OBSERVATIONS_API.md) endpoints require data this module doesn't own:

| Route | Implemented By | Why |
|-------|------------------|-----|
| `POST /observations` | **Observation module** | Owns ingestion |
| `GET /observations` | **Observation module** | Owns the entity, scoped to Organization |
| `GET /observations/{id}` | **Observation module (Observation fields only, Stage 3)** — the response's `prediction` field is populated later, by Analysis (Stage 4) | Composition of Observation + Prediction is deferred — see [`adr/ADR-003-prediction-composition-deferred.md`](./adr/ADR-003-prediction-composition-deferred.md) |
| `GET /agents/{agentId}/observations` | **Observation module** | Confirmed already in `docs/backend/agent/01-overview.md` §5 — this module implements it, not Agent |

---

## 7. Why Observation Is Its Own Module and Not Folded Into Agent

Considered and rejected, same reasoning shape as Agent-vs-Authentication in Stage 2. An Observation's lifecycle (ingest, validate, store, retrieve) has nothing to do with *who* an Agent is or how it authenticates — it's a distinct, extremely high-volume, write-heavy responsibility with its own performance characteristics (see the dedicated indexes in [`01-database/schema/indexes.md`](../01-database/01-database/schema/indexes.md)). Folding it into Agent would immediately create the God Module the baseline architecture rejects.

---

## 8. Session Summary

```text
Observation Module — Overview

Owns
✔ Observation entity
✔ raw_ases_json (source of truth, immutable)
✔ Initial analysis_status = PENDING

Does Not Own
✘ ML communication
✘ Risk / Verdict
✘ Alerts
✘ analysis_status transitions past PENDING

Golden Rule
✔ Observation has zero knowledge of Analysis, ML, or Alert.
✔ Its job is done the instant the row is persisted.
```
