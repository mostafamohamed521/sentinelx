# 02 — Analysis Domain

> Restates, for the implementation layer, exactly what is already frozen in [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §6 and [`ML_CONTRACT.md`](../docs/docs/05-integration/02-ML_CONTRACT.md). Adds no new schema, no new business rule.

---

## 1. The Prediction Entity

```text
Prediction
──────────────────────────
id                  UUID v7, PK
observation_id      FK → observations.id, UNIQUE   (0..1 — see §2)
verdict              SAFE | SUSPICIOUS | MALICIOUS, required
confidence            Decimal 0..1, required
risk_score             Integer 0..100, required
summary                  Text, required
model_version              String, required
prediction_json             JSONB, required — full ML response (Evidence, Reasons, Models, etc.)
analyzed_at                   timestamp, required — when the ML Engine finished
created_at                     timestamp
updated_at                      timestamp
```

`ON DELETE RESTRICT` on `observation_id`, `CHECK (0 <= risk_score <= 100)`, `CHECK (0 <= confidence <= 1)` — all already frozen in [`01-database/schema/constraints.md`](../01-database/01-database/schema/constraints.md) §6.

---

## 2. Cardinality: `Observation (1) → Prediction (0..1)`

Not `1..1`. Per [`01-database/schema/relationships.md`](../01-database/01-database/schema/relationships.md) §3: *"when an Observation first arrives, its state is PENDING with no Prediction attached yet."* A Prediction only ever comes into existence after this module successfully processes an Observation — there is no code path that creates a Prediction eagerly, speculatively, or as a placeholder.

**A `FAILED` Observation never gets a Prediction row.** Failure is recorded entirely on the Observation side (`analysis_status = FAILED`, via the write-back method already exposed in Stage 3) — see [`03-processing-pipeline.md`](./03-processing-pipeline.md) §5.

---

## 3. Why There Is No Separate `evidence` Table

Per [`ADR-013-Evidence-Based-Predictions`](../docs/docs/07-adrs/ADR-013-Evidence-Based-Predictions.md), every Prediction must include structured evidence — *"Matching events, Detection models, Threat categories, Confidence scores, Relevant references."* But per the already-frozen schema, none of this lives in a normalized `evidence` table; it lives entirely inside `prediction_json`:

```text
prediction_json (JSONB)
├── evidence[]        — matching events, detection models, threat categories
├── reasons[]          — human-readable explanation of the verdict
├── models[]             — which ML models contributed
├── datasets[]             — training/reference data referenced
├── mitre / owasp             — threat-framework mappings, if applicable
└── ... any other ML-response fields, exactly as the ML Engine returned them
```

**This module never decomposes `prediction_json` into relational columns.** The only fields promoted to real columns are `verdict`, `confidence`, `risk_score`, `summary`, and `model_version` — because [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §6 already justifies exactly these five as needed for Dashboard sorting/filtering/display. Everything else stays inside the JSON document, for the same reason `raw_ases_json` stays undecomposed on the Observation side ([`ADR-001-Canonical-Observation`](../docs/docs/07-adrs/ADR-001-Canonical-Observation.md)) — see [`adr/ADR-002-no-dedicated-evidence-table.md`](./adr/ADR-002-no-dedicated-evidence-table.md).

---

## 4. Field-Level Rules

| Field | Rule |
|-------|------|
| `verdict` | Copied verbatim from the ML Engine's response. Never computed, inferred, or adjusted by this module. |
| `confidence` | `0..1`, copied verbatim. |
| `risk_score` | `0..100`, copied verbatim. |
| `summary` | Copied verbatim — the ML Engine's own short explanation, never rewritten by this module. |
| `model_version` | Copied verbatim from the ML Engine's response — required precisely so a Prediction remains meaningful even after the model is retrained or replaced (per [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §6: *"very important for historical lookups"*). |
| `prediction_json` | The full, raw ML response body, stored exactly as received — this module's Infrastructure layer does not strip, rename, or reshape any of its keys before storing. |
| `analyzed_at` | Set by this module at the moment the ML Engine's response is received — not when the request was sent, and not `now()` at INSERT time if those differ (they normally won't, but the semantic is "when analysis finished," not "when the row was written"). |

---

## 5. Immutability — Same Principle as Observation, One Exception

Once written, a Prediction row is **never updated in place either** — matching [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §6: *"Once stored, it becomes part of the platform's audit history — even if the model changes later, we can go back and see how the old model saw this event."*

```text
If the ML model improves and re-analysis is desired later (a V2 concept, not built here):
  → a NEW Observation-analysis cycle would be required, or a versioned re-analysis
    concept would need its own ADR — NOT an UPDATE to an existing Prediction row.
```

Nothing in Stage 4 implements re-analysis. This module writes each Prediction exactly once, ever, per Observation.

---

## 6. Domain Invariants (Must Hold True At All Times)

```text
1. A Prediction exists only for an Observation whose analysis_status is (or was, en route
   to) COMPLETED — never for PENDING, PROCESSING, or FAILED (see §2).
2. observation_id is always unique — at most one Prediction per Observation, enforced by
   the database constraint, never merely assumed by application logic.
3. verdict, confidence, risk_score, summary, and model_version are always populated
   directly from the ML Engine's response — never defaulted, guessed, or left partially
   filled by this module.
4. prediction_json always contains the complete, unaltered ML response body.
5. A Prediction, once written, is never updated or deleted by this module.
```
