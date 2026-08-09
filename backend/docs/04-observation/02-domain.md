# 02 — Observation Domain

> Restates, for the implementation layer, exactly what is already frozen in [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §5 and [`docs.zip/03-specifications/`](../docs/docs/03-specifications/). Adds no new schema, no new business rule — only the implementation-level detail those files intentionally leave out.

---

## 1. The Observation Entity

```text
Observation
──────────────────────────
id                       UUID v7, PK
organization_id          FK → organizations.id   (denormalized — see §5)
agent_id                 FK → agents.id
analysis_status          PENDING | PROCESSING | COMPLETED | FAILED   — this module only ever writes PENDING
raw_ases_json             JSONB, required — the full ASES payload, byte-for-byte
received_at               timestamp, required — when the SDK's payload actually arrived
processing_started_at      timestamp, optional — never written by this module
processed_at                timestamp, optional — never written by this module
created_at                   timestamp
updated_at                    timestamp
```

`ON DELETE RESTRICT` on both foreign keys — no exceptions, platform-wide (see [`01-database/schema/constraints.md`](../01-database/01-database/schema/constraints.md) §8).

---

## 2. What ASES Actually Is

Per [`ASES_SPECIFICATION.md`](../docs/docs/03-specifications/01-ASES_SPECIFICATION.md): **A**gent **S**ecurity **E**vent **S**pecification — the canonical, framework-independent format every Agent's SDK must convert its native execution log into before submission. Three top-level sections, per [`ASES_JSON_SCHEMA.md`](../docs/docs/03-specifications/03-ASES_JSON_SCHEMA.md):

```text
raw_ases_json
├── context      — framework, agent_version, environment, execution start/finish time
├── events[]     — ordered array of what actually happened during execution
└── metadata     — ASES spec version, SDK version, generation timestamp
```

Each entry in `events[]` belongs to one of the ten canonical types defined in [`EVENT_DICTIONARY.md`](../docs/docs/03-specifications/02-EVENT_DICTIONARY.md) (`api_call`, `file_access`, `command_execution`, `network_connection`, `database_operation`, `tool_execution`, `memory_operation`, `authentication`, `configuration_change`, `custom`), each with a `header` and a `payload`.

**This module never inspects the semantic content of any event.** It confirms the JSON is shaped correctly (see [`04-validation.md`](./04-validation.md)) and stores it. What an `api_call` event's payload *means* from a security standpoint is exclusively the ML Engine's concern.

---

## 3. Immutability

Per [`ADR-004-Immutable-Observation-Storage`](../docs/docs/07-adrs/ADR-004-Immutable-Observation-Storage.md): once accepted, an Observation is never modified by anyone, human or system. There is no `PATCH /observations/{id}` endpoint anywhere in the frozen contract, and none should ever be added.

```text
Correction model:
  Incorrect Observation submitted  →  submit a NEW Observation
  (never edit the old one)
```

The **only** field that ever changes after the initial `INSERT` is `analysis_status` (and its accompanying `processing_started_at` / `processed_at` timestamps) — and per §5 of [`01-overview.md`](./01-overview.md), the Observation module itself never performs that update. See [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) §2.

---

## 4. No Decomposition Into Events Tables

Per [`ADR-001-Canonical-Observation`](../docs/docs/07-adrs/ADR-001-Canonical-Observation.md) and [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §5's own Key Decisions: *"No decomposition into Events — an old, confirmed decision from the start of the project. Events remain part of the full JSON."* There is no `events` table, no `observation_events` table, nothing of the sort. `raw_ases_json` is a single opaque JSONB document from this module's point of view.

**Implication for implementation:** the Application layer's `ReceiveObservationAction` does not iterate `events[]`, does not extract individual event rows, and does not build any relational representation of the payload. It validates the JSON's *shape* (see `04-validation.md`) and passes the whole document straight to the Repository for storage.

---

## 5. Why `organization_id` Is Denormalized Onto This Table

Already frozen, restated here because it directly shapes how the Application layer builds a new Observation record:

> "Nearly every query in the Dashboard starts with `organization_id`. It avoids a `JOIN` against the `agents` table in most read operations." — [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §5

**Implementation rule:** `organization_id` is never separately looked up by the Observation module at write time from some external source — it comes from the **same Authenticated Identity** that resolved the Agent (see [`03-ingestion-pipeline.md`](./03-ingestion-pipeline.md) §2), which already carries the Agent's `organization_id` per [`02-auth/contracts/api-key-format.md`](../02-auth/02-auth/contracts/api-key-format.md) §4's `AuthenticatedIdentity` shape. Both `agent_id` and `organization_id` are written from the exact same trusted value in the exact same request — never independently re-derived, and never accepted from the request body.

---

## 6. `analysis_status` — What This Module Owns vs. What It Doesn't

```text
PENDING     ← written ONCE, by this module, at INSERT time. Default value.
PROCESSING  ← written by Analysis (Stage 4) — never by this module
COMPLETED   ← written by Analysis (Stage 4) — never by this module
FAILED      ← written by Analysis (Stage 4) — never by this module
```

There is no state-machine logic to implement inside the Observation module for this column beyond "always `PENDING` at creation." The full state machine (see [`diagrams/observation-analysis-status-state.svg`](./diagrams/observation-analysis-status-state.svg)) belongs conceptually to the pipeline as a whole, but its write authority belongs entirely to Analysis, exactly mirroring how `agents.last_seen_at` is a column this module (Observation) writes to on `agents`, a table it doesn't own — see [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) §1 for the reverse case.

---

## 7. Domain Invariants (Must Hold True At All Times)

```text
1. Every Observation belongs to exactly one Agent and, denormalized, exactly one
   Organization — and that Organization must always equal the Agent's own
   organization_id (never independently settable).
2. raw_ases_json is never NULL, never empty, and never modified after insert.
3. analysis_status is PENDING at the moment of creation — always, no exceptions.
4. received_at reflects when the SDK's payload actually arrived, independent of
   created_at (they may coincide in practice but are conceptually distinct).
5. An Observation from an ARCHIVED Agent can never be created — enforced at
   Authentication, before this module is ever reached (see 06-authorization.md §3).
```
