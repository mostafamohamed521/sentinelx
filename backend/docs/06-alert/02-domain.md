# 02 — Alert Domain

> Restates, for the implementation layer, exactly what is already frozen in [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §7 and [`enums.md`](../01-database/01-database/schema/enums.md) §8–9. Resolves the two documentation gaps named in [`README.md`](./README.md) §4, explicitly, with dedicated ADRs.

---

## 1. The Alert Entity

```text
Alert
──────────────────────────
id                  UUID v7, PK
prediction_id        FK → predictions.id, UNIQUE   (0..1 from Prediction's side — see §2)
severity              LOW | MEDIUM | HIGH | CRITICAL, required
status                  OPEN | ACKNOWLEDGED | RESOLVED, required, default OPEN
acknowledged_at           timestamp, optional
acknowledged_by            FK → users.id, optional
resolved_at                  timestamp, optional
resolved_by                    FK → users.id, optional
created_at                      timestamp
updated_at                        timestamp
```

`ON DELETE RESTRICT` on `prediction_id`, `CHECK (severity IN (...))`, `CHECK (status IN (...))` — all already frozen in [`01-database/schema/constraints.md`](../01-database/01-database/schema/constraints.md) §7.

---

## 2. Cardinality: `Prediction (1) → Alert (0..1)`

Per [`01-database/schema/relationships.md`](../01-database/01-database/schema/relationships.md) §3: *"Optional, because a `SAFE` verdict will never produce an Alert."* `UNIQUE(prediction_id)` guarantees at most one Alert per Prediction — this module never creates a second Alert for a Prediction that already has one (see [`03-generation-pipeline.md`](./03-generation-pipeline.md) §3 for the idempotency guard).

---

## 3. Gap Resolution — Severity Threshold Mapping

No frozen document defines the numeric `risk_score` → `severity` mapping. Per [`adr/ADR-001-severity-threshold-mapping.md`](./adr/ADR-001-severity-threshold-mapping.md), this folder adopts:

```text
risk_score  0 – 24   → LOW
risk_score 25 – 49    → MEDIUM
risk_score 50 – 74     → HIGH
risk_score 75 – 100      → CRITICAL
```

**Explicitly flagged as an engineering default**, exactly like Analysis's retry policy ([`docs/backend/analysis/adr/ADR-003-ml-failure-retry-then-fail.md`](../05-analysis/adr/ADR-003-ml-failure-retry-then-fail.md)) — even bucket boundaries, easy to reason about, and trivially adjustable later without any structural change, since [`ADR-011-Alert-Generation-Policy`](../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md) itself anticipates *"future platform versions may allow organizations to configure custom alert thresholds."* This module's `SeverityMapper` (Domain layer) is a single, isolated function specifically so replacing these thresholds — or making them Organization-configurable later — touches one place, not a spray of conditionals.

---

## 4. The Generation Policy (V1, Fixed)

```text
verdict = SAFE          → no Alert, ever
verdict = SUSPICIOUS      → Alert created, severity = SeverityMapper(risk_score)
verdict = MALICIOUS         → Alert created, severity = SeverityMapper(risk_score)
```

Per [`01-database/schema/enums.md`](../01-database/01-database/schema/enums.md) §7: *"`SAFE` — The event is safe — will never produce an Alert."* By construction, the only verdicts that reach this module's policy evaluator at all are `SUSPICIOUS` and `MALICIOUS` — both always produce an Alert in V1. There is no additional risk-score gate on top of the verdict check (e.g., "only alert if `MALICIOUS` AND `risk_score > 80`") — no frozen document suggests such a gate exists, and inventing one would be exactly the kind of undocumented business rule this series avoids introducing.

---

## 5. State Machine

```text
        Alert created (verdict != SAFE)
              │
              ▼
          ┌────────┐
          │  OPEN  │
          └───┬────┘
              │ acknowledge
              ▼
       ┌───────────────┐
       │ ACKNOWLEDGED  │
       └───────┬───────┘
               │ resolve
               ▼
          ┌───────────┐
          │ RESOLVED  │  (terminal — see Gap 2 / ADR-002)
          └───────────┘
```

**Allowed transitions:**
```text
OPEN          → ACKNOWLEDGED   ✔ via PATCH /alerts/{id}/acknowledge
OPEN          → RESOLVED       ✔ via PATCH /alerts/{id}/resolve (skip-ahead allowed —
                                    no business rule requires acknowledging first;
                                    see 03-generation-pipeline.md §4)
ACKNOWLEDGED  → RESOLVED       ✔ via PATCH /alerts/{id}/resolve
RESOLVED      → anything        ✘ terminal, in V1 — see Gap 2 / ADR-002
ACKNOWLEDGED  → ACKNOWLEDGED     idempotency: a second acknowledge call returns 409, not
                                    a silent success — same discipline already established
                                    for Agent Archive in Stage 2
RESOLVED      → RESOLVED          same idempotency rule — 409, not silent success
```

See the rendered version at [`diagrams/alert-status-state.svg`](./diagrams/alert-status-state.svg).

---

## 6. Why `RESOLVED` Is Terminal in V1 (Gap 2)

Per [`enums.md`](../01-database/01-database/schema/enums.md) §9: *"Deliberately not added: `ARCHIVED` — because archiving is a storage strategy, not a business state."* Combined with the fact that [`ALERTS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md) exposes no `reopen` route at all, this module treats `RESOLVED` as terminal for V1 — even though [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7 lists `Reopen` among this module's named responsibilities. See [`adr/ADR-002-no-reopen-endpoint-in-v1.md`](./adr/ADR-002-no-reopen-endpoint-in-v1.md) for the full resolution of this discrepancy — the short version: **build what the API contract exposes, flag the mismatch, don't guess which frozen document is the stale one.**

---

## 7. `acknowledged_by` / `resolved_by` — Not Explicitly in the Schema Excerpt, Confirmed Necessary

[`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) §7 and [`constraints.md`](../01-database/01-database/schema/constraints.md) §7 name `severity` and `status` as the only `NOT NULL` business columns beyond the standard FK/PK/timestamps, but [`ALERTS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md)'s acknowledge/resolve actions clearly represent a specific Human taking an action — per general platform convention (every other mutation in this system, e.g. Agent Archive, records *who* acted, even where a document doesn't spell out a dedicated audit column, because [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §9 names a dedicated Audit module specifically for *cross-cutting* event recording, not per-row "who did this" attribution). `acknowledged_by`/`resolved_by` (nullable FKs to `users.id`) are included here as **optional, nullable columns** already implied by any reasonable reading of "acknowledge/resolve" as a Human action, not as a new architectural decision requiring its own ADR — consistent with how `docs/backend/agent/02-domain.md` treated `last_seen_at` as an implied, necessary column even though its exact placement needed its own write-authority discussion.

---

## 8. Domain Invariants (Must Hold True At All Times)

```text
1. An Alert exists only for a Prediction whose verdict is SUSPICIOUS or MALICIOUS —
   never SAFE.
2. prediction_id is always unique — at most one Alert per Prediction (database-enforced).
3. severity is always derived from risk_score via SeverityMapper — never set directly,
   never client-suppliable.
4. status starts at OPEN and only ever moves forward (OPEN → ACKNOWLEDGED → RESOLVED,
   or OPEN → RESOLVED directly) — never backward, in V1.
5. acknowledged_at/acknowledged_by are set together, exactly once, on the first
   successful acknowledge call — never overwritten by a second one (which is instead
   rejected as 409).
6. resolved_at/resolved_by follow the identical rule for resolve.
```
