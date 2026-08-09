# SentinelX — Alert Module Documentation

> **Status:** 🟢 **Active Design — Stage 5 of the Implementation Order**
> **Depends on (Frozen):** `docs.zip` (Documentation Baseline v2.0), `docs/backend/database/`, `docs/backend/backend-architecture/`
> **Also builds on:** `docs/backend/agent/`, `docs/backend/observation/`, `docs/backend/analysis/` (all Baseline v1.0)
> **Owner:** Backend Architecture Team
> **Extends, never conflicts with:** the frozen root documentation. Every rule here is a direct implementation of an already-frozen decision — most importantly [`ADR-011-Alert-Generation-Policy`](../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md) and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7.
>
> **Two genuine gaps in the frozen documentation are resolved here, explicitly and visibly, rather than silently:** the exact risk-score → severity mapping, and a real inconsistency between two frozen documents about whether a "Reopen" action exists. See §4 below before reading further — both are called out again, in full, in this folder's own ADRs.

---

## 1. Why does this folder exist?

Same reason `02-auth`, `03-agent`, `04-observation`, and `05-analysis` exist. Not a tutorial — the **Source of Truth** an engineer (human or Claude Code) reads before writing a single line of the Alert module.

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins — except for the two specific gaps named in §4, which `docs.zip` itself does not resolve, and which this folder resolves explicitly, with reasoning, not silently.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      ✅ Done
Stage 3 — Observation Pipeline           ✅ Done
Stage 4 — Analysis (ML Integration)      ✅ Done
Stage 5 — Alert Engine                   🟢 THIS FOLDER
Stage 6 — Dashboard                      ⏳ Next
Stage 7 — Audit & Settings               ⏳
```

Per [`backend-architecture/08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §7, **Definition of Done for this Sprint: "the first real Alert appears."**

---

## 3. The Core Idea in One Sentence

> **The Alert module's entire job is to notice a completed, non-`SAFE` Prediction, translate its numeric risk into an operational severity, and give a Human somewhere to say "seen it" and "handled it" — it never re-judges the security assessment itself.**

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7, this is literally called *"the simplest module."* Per §14: *"`Alert` never computes a Risk Score — `Analysis` does."*

---

## 4. The Two Documentation Gaps This Folder Resolves

Being explicit about these, rather than quietly picking an answer, is the whole point of this documentation series.

### Gap 1 — No risk-score-to-severity mapping exists anywhere in the frozen docs
[`01-database/schema/enums.md`](../01-database/01-database/schema/enums.md) §8 defines `Severity` (`LOW`/`MEDIUM`/`HIGH`/`CRITICAL`) and explains *why* it's separate from `risk_score`, but no document anywhere specifies the numeric thresholds. This folder proposes a specific mapping, clearly flagged as an engineering default — see [`adr/ADR-001-severity-threshold-mapping.md`](./adr/ADR-001-severity-threshold-mapping.md).

### Gap 2 — Two frozen documents disagree about whether "Reopen" exists
[`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §7 lists `Reopen` among this module's responsibilities. [`OBSERVATIONS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md)'s sibling, [`ALERTS_API.md`](../docs/docs/09-api-reference/05-ALERTS_API.md), exposes exactly four endpoints — `GET /alerts`, `GET /alerts/{id}`, `PATCH .../acknowledge`, `PATCH .../resolve` — and no `reopen` route at all. This folder builds only what the API contract actually exposes and flags the discrepancy rather than guessing which document is stale — see [`adr/ADR-002-no-reopen-endpoint-in-v1.md`](./adr/ADR-002-no-reopen-endpoint-in-v1.md).

---

## 5. Module Boundary Recap (from the Frozen Baseline)

```text
Alert Module                                  Analysis Module (Stage 4, already frozen)
────────────────────                          ───────────────────────────────────────────────
✔ Alert (entity)                              ✔ Prediction (entity) — read-only to Alert
✔ Alert Status (state machine)                 ✔ Exposes PredictionLookupContract (already
✔ Resolution                                       built in Stage 4, unused until now)
                                                 ✔ Will dispatch PredictionStored — the one
✘ Does NOT perform analysis                        small, additive change this Sprint requires
✘ Does NOT communicate with ML                       of Analysis (see 05-cross-module-boundaries.md)
✘ Does NOT own Prediction
```

Dependency direction (per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §7, unchanged here):

```text
Alert
    ↓
Analysis
    ↓
Observation
    ↓
Agent
    ↓
Organization
```

**Alert depends on Analysis. Analysis does not depend on Alert, and never will.** This is the same shape established three times already — see [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) for exactly how it plays out here.

---

## 6. Folder Architecture

```text
06-alert/
│
├── README.md                          ← you are here
│
├── 01-overview.md                     ← What the Alert module is (and isn't)
├── 02-domain.md                       ← The Alert entity, Severity, the state machine
├── 03-generation-pipeline.md          ← Event → Policy Evaluation → Create Alert
├── 04-authorization.md                ← Who can view, acknowledge, resolve
├── 05-cross-module-boundaries.md      ← Alert ↔ Analysis (consumer), Alert ↔ Dashboard (forward)
├── 06-api-contract.md                 ← The 4 real endpoints, exactly as frozen
├── 07-implementation-roadmap.md       ← Build order, Sprint 5 breakdown
│
├── adr/
│   ├── ADR-001-severity-threshold-mapping.md
│   ├── ADR-002-no-reopen-endpoint-in-v1.md
│   └── ADR-003-all-roles-can-act-on-alerts.md
│
└── diagrams/
    ├── alert-status-state.svg
    └── alert-generation-sequence.svg
```

---

## 7. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What the Alert module owns, and the one rule it never breaks |
| 2 | [`02-domain.md`](./02-domain.md) | The Alert entity, Severity mapping, the OPEN→ACKNOWLEDGED→RESOLVED state machine |
| 3 | [`03-generation-pipeline.md`](./03-generation-pipeline.md) | How a Prediction becomes an Alert — event-driven, not polling |
| 4 | [`04-authorization.md`](./04-authorization.md) | Who can view, acknowledge, and resolve |
| 5 | [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) | The one new thing Analysis must expose, and what Alert exposes for Dashboard |
| 6 | [`06-api-contract.md`](./06-api-contract.md) | The exact 4 endpoints, request/response shapes |
| 7 | [`07-implementation-roadmap.md`](./07-implementation-roadmap.md) | Build order inside Sprint 5, mapped to Layers |
| — | [`adr/`](./adr) | The three pivotal decisions, including both documentation-gap resolutions |
| — | [`diagrams/`](./diagrams) | State diagram, generation sequence diagram |

---

## 8. What This Folder Deliberately Does NOT Redefine

```text
alerts table schema                         → 01-database/schema/entities.md §7
Severity / AlertStatus enum values           → 01-database/schema/enums.md §8-9
Alert Generation Policy (who decides)        → docs.zip/07-adrs/ADR-011-Alert-Generation-Policy.md
Error response shape                        → docs.zip/09-api-reference/07-ERROR_CODES.md
Layer structure (API→…→Presentation)         → backend-architecture/06-implementation-layers.md
Analysis's exposed contracts                 → docs/backend/analysis/05-cross-module-boundaries.md
                                                 (this folder only consumes what's already declared there,
                                                 plus the one addition documented in this folder's own
                                                 05-cross-module-boundaries.md)
```

---

## 9. Design Status

```text
Alert Module Design
████████████████████████████ 100% (ready for implementation)

Overview                    ✅ Frozen
Domain                      ✅ Frozen
Generation Pipeline         ✅ Frozen
Authorization               ✅ Frozen
Cross-Module Boundaries     ✅ Frozen
API Contract                ✅ Frozen
Implementation Roadmap      ✅ Frozen
```

> As with the four folders before it, once this folder is used to generate code, it becomes frozen too.
