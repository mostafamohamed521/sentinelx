# ADR-003: Storing Observation and Prediction as JSONB Instead of Decomposing into Tables

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Session** | Session 1 (initial decision) + Session 6, 7 (final confirmation) |
| **Affects** | `observations.raw_ases_json`, `predictions.prediction_json` |

---

## Context

An Observation (from the SDK) and a Prediction (from ML) both carry rich, relatively complex data: sequential Events, Evidence, Reasons, Models, Datasets, MITRE/OWASP classifications, etc.

Two main options existed:

1. **Full Decomposition (Full Normalization):** every Event and every piece of Evidence becomes a row in a separate table (`events`, `evidence`, etc.).
2. **Document Storage:** save the full JSON exactly as received, and only extract columns actually needed for querying.

---

## Decision

**JSON (specifically JSONB in PostgreSQL) — not decomposition into tables.**

```text
observations.raw_ases_json     → Full ASES JSON (Source of Truth)
predictions.prediction_json    → Full ML response (Evidence, Reasons, Models...)
```

With only the columns that are actually and repeatedly queried, filtered, or sorted on extracted separately:

```text
observations → analysis_status, received_at, processing_started_at, processed_at
predictions   → verdict, confidence, risk_score, summary, model_version, analyzed_at
```

This model is called a **Hybrid Data Model**, governed by the principle:

> **Store for Query, Keep the Rest as Document.**

---

## Rationale

### 1. Preserving the Original Shape (Source of Truth)
Decomposing the ASES JSON into dozens of rows in relational tables would lose:
- The precise original ordering of events.
- The ease of re-analysis using exactly the same data as it originally arrived.
- The original shape as evidence, retrievable for any future security investigation.

### 2. Flexibility Against Schema Evolution
The shape of Evidence coming from ML "can change as the model evolves." If we constrained ourselves to rigid relational tables, any change in model output would require a new database migration. JSON absorbs this change without any structural modification.

### 3. No Real Need to Query Inside the JSON Content
No endpoint in the REST API queries *inside* the content of the Evidence or Events themselves. All actual queries operate at the metadata level (status, dates, scores), and these have already been extracted as separate columns.

### 4. This Is the Pattern Used in Modern Logging Systems
Storing the full event as a document, while extracting only queryable fields as columns, is a proven, well-established pattern in modern monitoring and security systems.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| A separate `events` table for each event inside the Observation | Loses the original ordering and context of events, and complicates querying and maintenance with no real benefit |
| A separate `evidence` table for each piece of evidence in the Prediction | The shape of Evidence changes as the model evolves — freezing its shape into rigid tables is impractical |
| Plain JSON (instead of JSONB) | JSONB in PostgreSQL is faster for search, supports indexing, and is better for storage |

---

## Consequences

- ✅ Full protection of the integrity of the original data as security evidence.
- ✅ No need for a new migration every time ML's output shape changes.
- ✅ Dashboard queries are fast because they rely on extracted columns, not decomposing JSON at read time.
- ⚠️ Currently cannot perform Full Text Search or query directly *inside* the JSON content (a conscious decision — see [`schema/indexes.md`](../schema/indexes.md#5-broader-performance-related-decisions)) — if a real need arises in the future, a GIN index can be added on the JSONB columns without any structural change.
