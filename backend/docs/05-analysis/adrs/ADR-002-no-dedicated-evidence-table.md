# ADR-002: Evidence Lives Inside `prediction_json`, Never in a Dedicated Table

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Analysis Module |
| **Affects** | `predictions` table shape, `MLClient` → `PredictionRepository` write path |

---

## Context

[`ADR-013-Evidence-Based-Predictions`](../../docs/docs/07-adrs/ADR-013-Evidence-Based-Predictions.md) mandates that every Prediction include structured evidence — matching events, detection models, threat categories, confidence scores, references. A natural implementation instinct, especially once "structured" is in the requirement, is to model this as a proper relational `evidence` table (one-to-many from `predictions`), enabling queries like "find all Predictions with evidence referencing MITRE technique X." The frozen schema ([`01-database/schema/entities.md`](../../01-database/schema/entities.md) §6) already answers this: Evidence lives inside `prediction_json` (JSONB), not a separate table.

---

## Decision

Evidence, Reasons, Models, Datasets, and any other ML-response detail beyond the five promoted columns (`verdict`, `confidence`, `risk_score`, `summary`, `model_version`) are stored exactly as the ML Engine returned them, inside `prediction_json`. No `evidence`, `prediction_evidence`, or similarly-named table is created.

---

## Rationale

### Doesn't "structured evidence" imply a relational structure?
"Structured" in [`ADR-013-Evidence-Based-Predictions`](../../docs/docs/07-adrs/ADR-013-Evidence-Based-Predictions.md) describes the *shape of the data the ML Engine returns* (a well-formed JSON object with named fields), not a mandate for how the Backend persists it. JSONB is itself a structured storage format — queryable, indexable if ever needed — without requiring a fixed relational schema that would need to change every time the ML Engine's evidence format evolves.

### Why does this matter given `ADR-014-Stable-ML-Contract` already exists?
Precisely because the ML Contract is meant to evolve independently (per [`ADR-014-Stable-ML-Contract`](../../docs/docs/07-adrs/ADR-014-Stable-ML-Contract.md)), a relational `evidence` table would require a schema migration — and therefore Backend coordination — every time the ML Engine's evidence structure changes in a way that isn't purely additive to a fixed column set. Storing the whole response as JSONB means the ML Engine's team can add, remove, or restructure evidence fields inside their own response payload without ever requiring a Backend migration, exactly the same reasoning already applied to `raw_ases_json` on the Observation side ([`ADR-001-Canonical-Observation`](../../docs/docs/07-adrs/ADR-001-Canonical-Observation.md)).

### What if the Dashboard later needs to query "all Predictions citing MITRE technique T1059"?
Not a V1 requirement, and not blocked by this decision — PostgreSQL's JSONB type supports GIN indexing directly on JSON content if and when that query pattern becomes real (see [`01-database/schema/indexes.md`](../../01-database/schema/indexes.md) §5: *"JSON Indexing? ❌ No — There's no direct query into the content of the JSON — it's stored as a full document only"* for V1, explicitly leaving this door open rather than closed).

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Dedicated `evidence` table, one-to-many from `predictions` | Contradicts the already-frozen `predictions` schema, which has no such table; would require Backend migrations every time the ML Engine's evidence shape changes |
| Partial decomposition — promote a few "important" evidence fields (e.g., `mitre_technique`) to columns, keep the rest in JSON | No frozen document identifies which evidence fields deserve this treatment, and doing so speculatively risks guessing wrong and needing a migration anyway once real usage patterns emerge |
| Store evidence as a separate JSONB column (`evidence_json`) apart from the rest of the ML response | Splits one atomic ML response across two columns for no documented benefit — the existing `prediction_json` already accommodates the full response as one coherent document |

---

## Consequences

- ✅ The ML Engine team can evolve evidence structure freely without coordinating a Backend schema migration for every change.
- ✅ Consistent with the identical, already-frozen decision for `raw_ases_json` on the Observation side — one recurring pattern across the platform, not two different philosophies for two similar JSON payloads.
- ✅ `prediction_json` remains queryable via PostgreSQL's native JSONB operators if a real need arises later, without requiring this decision to be revisited.
- ⚠️ Any future Dashboard feature needing to filter/aggregate by evidence content will need its own design work (likely GIN indexing on `prediction_json`, or a purpose-built read model) — explicitly deferred, not solved here.
