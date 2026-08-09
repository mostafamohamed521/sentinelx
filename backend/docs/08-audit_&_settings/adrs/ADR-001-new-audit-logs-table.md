# ADR-001: A New `audit_logs` Table Is Added — the Only Exception in This Entire Series

| | |
|---|---|
| **Status** | ✅ Accepted (Genuine Schema Addition — Not a Silent Override of the Frozen 7-Table Design) |
| **Scope** | Audit Module |
| **Affects** | The database schema, for the first and only time in this documentation series |

---

## Context

Every prior module in this series — Agent, Observation, Analysis, Alert, Dashboard — was built entirely on top of the frozen, exhaustively-confirmed **7-table schema** (`organizations`, `users`, `agents`, `api_keys`, `observations`, `predictions`, `alerts`), explicitly titled *"Overview of the Seven Tables"* in [`01-database/schema/entities.md`](../../01-database/01-database/schema/entities.md). This series has repeatedly stated, in every prior module's implementation roadmap, *"no new migration needed — it already exists."*

That statement cannot be made here. [`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §9 names an `Audit` module that *"Owns: Audit Events"* and is *"Responsible For: Recording events, Searching events, Displaying events."* §10 of the same document even lists a write-ownership row: *"Audit → Audit Logs."* But no table for this data exists anywhere in the frozen schema — this is a genuine omission, not a decision this ADR is contradicting.

---

## Decision

A new `audit_logs` table is added, per the shape specified in [`02-domain.md`](../02-domain.md) §1. This is explicitly the **only** new table introduced anywhere across this entire documentation series (Stages 2 through 7).

---

## Rationale

### Why is adding a new table acceptable here, when every other Sprint treated the schema as strictly frozen?
Because "frozen" in this series has always meant *"do not silently change what already exists"* — it has never meant *"the schema can never grow to cover a responsibility the architecture documents themselves already named but never got around to specifying."* `04-module-responsibilities.md` unambiguously describes Audit as owning real, persisted data (*"Audit Events,"* *"Audit Logs"*) — the schema's silence on this point is best read as an oversight in the original database design pass (which, per its own file's title, focused on exactly seven specific tables and never circled back to Audit), not as an implicit decision that Audit should have no persistence layer at all.

### Why not represent Audit Events purely as application logs (per `LOGGING_STRATEGY.md`) instead of a database table?
Considered. [`LOGGING_STRATEGY.md`](../../docs/docs/10-operational-architecture/03-LOGGING_STRATEGY.md) exists, but its entire content is scoped to Observation/ASES logging (*"Canonical Observation,"* *"SentinelX adopts the ASES Observation Schema..."*) — it says nothing about administrative audit trails, and reusing it would conflate two unrelated logging concerns under one document that was never written with the second one in mind. More importantly, `04-module-responsibilities.md` §9 explicitly lists **"Searching events"** as a responsibility — a `GET /audit-logs?actor_id=...&action=...` style query is a natural, fast, filterable operation against a relational table, and a poor fit for grepping through unstructured or even structured application log files at request time. A database table is the correct persistence choice for data that must be queried, filtered, and paginated through a real API endpoint, which is exactly what `08-api-contract.md` specifies.

### Why is this the only table this series ever adds, given how many genuine gaps this Sprint alone surfaced?
Because every other gap in this Sprint (Security Logs, the Organization/Profile split) was resolvable by composing existing data or adding narrow read contracts — exactly the pattern used throughout this series. Only Audit's core data had no home anywhere. Every other module's core entity already existed in the frozen 7; only Audit's did not.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Represent Audit Events purely via structured application logs, no database table | `04-module-responsibilities.md` §9 explicitly requires "Searching events" as a first-class capability, poorly served by log files; also conflates with `LOGGING_STRATEGY.md`'s unrelated ASES-logging scope |
| Skip building the Audit module's persistence entirely, treat it as out of scope for the MVP | Directly contradicts the frozen `08-sprint-roadmap.md` §9, which names Audit as required for *"the system is ready as a complete MVP"* — this isn't a nice-to-have this series can simply omit |
| Store audit events inside `prediction_json`-style JSONB columns on existing tables (e.g., an `audit_trail` JSONB column on `organizations`) | Scatters what should be one unified, queryable, cross-entity log across multiple unrelated tables, defeating the entire purpose of a centralized audit trail; also has no natural home on tables like `agents` or `alerts` that also need auditing |

---

## Consequences

- ✅ Audit's own frozen responsibilities (`04-module-responsibilities.md` §9) are fully satisfiable — recording, searching, and displaying events all have a real, queryable data store.
- ✅ This exception is singular, named, and justified — not a precedent that quietly reopens "frozen means whatever I want it to mean" for future Sprints. Nothing beyond this one table should ever be added without the same level of explicit justification.
- ⚠️ This is a genuine schema change requiring an actual new migration — unlike every prior Sprint's implementation roadmap, this one's exit checklist correctly does NOT say "no new migration needed."
