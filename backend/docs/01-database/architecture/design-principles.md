# Design Principles

> This is the file any new engineer (or Claude Code) should read first, before touching any migration.
> Every decision in `schema/` and `decisions/` is a **direct application** of one of the principles below.

---

## 1. Observation is Immutable

Once an Observation is stored, it is **locked**. It is never edited afterward.

**Why?** Because it's a Fact — a security event that actually happened. Editing it would undermine its credibility as an audit record.

**In practice:** There is no `UPDATE` on `raw_ases_json` after the initial insert. The only columns that update after creation are the lifecycle columns themselves (`analysis_status`, `processing_started_at`, `processed_at`).

---

## 2. Prediction is Derived

A Prediction isn't original data — it's an **analysis result** based on an Observation at a specific point in time.

**Why?** Because the model itself can change over time. If we re-analyze the same Observation with a newer model, the result will differ — and that's expected, not a contradiction.

**In practice:** Every Prediction carries `model_version` — so a year from now we can answer "how did the old model see this event?" Predictions are also immutable after analysis.

---

## 3. Alert is Business State

An Alert isn't an analysis — it's an **operational decision (Business Event)** produced by the platform's policy based on the Prediction's result.

**Why?** Separating "analysis" from "decision" gives us flexibility — we can change the Alert-creation policy (e.g., raise the minimum risk_score threshold) without touching the ML logic at all.

**In practice:** `alerts` is a separate table with its own lifecycle (`OPEN → ACKNOWLEDGED → RESOLVED`), completely distinct from the Prediction's status.

---

## 4. Archive Instead of Delete

There is no physical delete in any business flow.

**Why?** SentinelX is a Security & Audit platform — history matters more than disk space. Physically deleting data means losing potential evidence that might be needed in a future investigation.

**In practice:**
```text
Organization  → SUSPENDED  (not DELETE)
Agent    → ARCHIVED    (not DELETE)
Alert    → RESOLVED    (not DELETE)
```
There is no `deleted_at` column in any table. See [`decisions/adr-002-soft-delete-strategy.md`](../decisions/adr-002-soft-delete-strategy.md).

---

## 5. UUID Everywhere

Every Primary Key in the system is `UUID v7`. There is no auto-increment integer in any table.

**Why?** The platform is public-facing, and no one should be able to guess IDs (`/organization/1`, `/organization/2`...). v7 specifically because it's time-ordered and faster in indexes than v4.

See [`decisions/adr-001-uuid-strategy.md`](../decisions/adr-001-uuid-strategy.md).

---

## 6. JSON as Source of Truth (where it belongs)

Data coming from external, variable-shape sources (SDK, ML) is stored as **full JSONB**, not broken up into tables.

**Why?** Breaking up the ASES JSON or the ML evidence into relational tables would lose:
- The original event ordering.
- Ease of re-analysis.
- The original shape, which may be needed as evidence.

**In practice:** Only `observations.raw_ases_json` and `predictions.prediction_json` are JSONB. No third JSON table exists. See [`decisions/adr-003-json-storage.md`](../decisions/adr-003-json-storage.md).

---

## 7. Indexes Follow Access Patterns

No index is added "because it might help" — every index serves a **real query** that actually exists in the REST API.

**Rule:** *Every Index Must Pay for Itself.*

**In practice:** Every index is documented in [`schema/indexes.md`](../schema/indexes.md) with the specific query it serves. No index exists without a written reason.

---

## 8. No Over Engineering

The hardest decision isn't "what to add" — it's "what to deliberately refuse to add right now."

**In practice:** These decisions were consciously made and deferred to V2 or later:

```text
❌ Event Table              ❌ Roles / Permissions Tables
❌ Audit Logs Table          ❌ API Key Scopes
❌ Webhooks                  ❌ Soft Deletes (deleted_at)
❌ Partitioning               ❌ Event Sourcing / CQRS
❌ Full Text Search           ❌ JSON Indexing
❌ Read Replicas              ❌ Materialized Views
```

Each of these has a specific rejection reason, not a lack of knowledge — details in [`decisions/`](../decisions) and [`implementation/implementation-notes.md`](../implementation/implementation-notes.md).

---

## 9. Normalize by Default, Denormalize with Purpose

The general rule: normalize. But if denormalization clearly and measurably improves a real, frequent query, we do it deliberately.

**Real example:** `observations.organization_id` exists even though it can be derived via `agent_id → agents.organization_id`. This decision was made because nearly every query in the Dashboard begins with `organization_id`, and avoiding the repeated JOIN measurably improves performance.

**Full rule:**
> Normalize by default... Denormalize only when it clearly improves real business queries.

---

## 10. Parent Must Always Exist (Referential Integrity)

Every record in the system must have a valid parent. No Foreign Key can be `NULL` except in cases we agreed on logically (like the optional existence of a Prediction).

**In practice:**
```text
Observation.agent_id       → the Agent must exist
Prediction.observation_id  → the Observation must exist
Alert.prediction_id        → the Prediction must exist
```
Every relationship is mandatory except where explicitly agreed to be optional (whether a Prediction or Alert exists at all). But **when the record exists, its parent must always exist.** All foreign keys use `ON DELETE RESTRICT` — no `CASCADE` and no `SET NULL` on any business relationship. See [`schema/relationships.md`](../schema/relationships.md).
