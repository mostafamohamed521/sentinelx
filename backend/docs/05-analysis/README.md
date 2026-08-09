# SentinelX — Analysis Module Documentation

> **Status:** 🟢 **Active Design — Stage 4 of the Implementation Order**
> **Depends on (Frozen):** `docs.zip` (Documentation Baseline v2.0), `docs/backend/database/`, `docs/backend/backend-architecture/`
> **Also builds on:** `docs/backend/agent/` and `docs/backend/observation/` (both Baseline v1.0) — Stage 4 cannot exist without Stage 2 and Stage 3
> **Owner:** Backend Architecture Team
> **Extends, never conflicts with:** the frozen root documentation. Every rule here is a direct implementation of an already-frozen decision — most importantly [`ADR-003-ML-Responsibility`](../docs/docs/07-adrs/ADR-003-ML-Responsibility.md), [`ADR-006-Backend-as-Orchestrator`](../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md), [`ADR-013-Evidence-Based-Predictions`](../docs/docs/07-adrs/ADR-013-Evidence-Based-Predictions.md), [`ADR-014-Stable-ML-Contract`](../docs/docs/07-adrs/ADR-014-Stable-ML-Contract.md), and [`ADR-015-Observation-Prediction-Separation`](../docs/docs/07-adrs/ADR-015-Observation-Prediction-Separation.md).

---

## 1. Why does this folder exist?

Same reason `02-auth`, `03-agent`, and `04-observation` exist. Not a tutorial — the **Source of Truth** an engineer (human or Claude Code) reads before writing a single line of the Analysis module.

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      ✅ Done (docs/backend/agent/)
Stage 3 — Observation Pipeline           ✅ Done (docs/backend/observation/)
Stage 4 — Analysis                       🟢 THIS FOLDER
Stage 5 — Alert                          ⏳ Next
Stage 6 — Dashboard                      ⏳
Stage 7 — Audit & Settings               ⏳
```

Per [`backend-architecture/08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §6, **Definition of Done for this Sprint: "the first real Prediction exists."**

---

## 3. The Core Idea in One Sentence

> **The Analysis module's entire job is to find Observations waiting for analysis, hand them to the ML Engine exactly as they are, and durably record whatever comes back — nothing about the security verdict is ever decided inside this module's own code.**

Per [`ADR-003-ML-Responsibility`](../docs/docs/07-adrs/ADR-003-ML-Responsibility.md): *"Threat classification, evidence generation, and risk scoring are responsibilities of the ML Engine only. The Backend stores and orchestrates data but does not classify threats."*

---

## 4. Module Boundary Recap (from the Frozen Baseline)

```text
Analysis Module                              Observation Module (Stage 3, already frozen)
────────────────────                         ───────────────────────────────────────────────
✔ Prediction (entity)                        ✔ Observation (entity) — read-only to Analysis
✔ prediction_json (Evidence lives inside)     ✔ analysis_status writes exposed FOR Analysis
✔ Drives observations.analysis_status          (markProcessing / markCompleted / markFailed —
   transitions (via Observation's own                already built in Stage 3, unused until now)
   exposed write methods)

✘ Does NOT own Observation                     Alert Module (Stage 5, does not exist yet)
✘ Does NOT decide risk/verdict itself          ────────────────────────────────────────────
✘ Does NOT create Alerts                        ✘ Does NOT exist yet — Analysis exposes a
                                                    read-only surface for it, unused until Stage 5
```

Dependency direction (per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md), unchanged here):

```text
Analysis
    ↓
Observation
    ↓
Agent
    ↓
Organization

Alert (Stage 5)
    ↓
Analysis
```

**Analysis depends on Observation. Alert will depend on Analysis. Analysis does not depend on Alert, and never will.** This is the exact same shape already established twice — Agent↔API Key in Stage 2, Observation↔Analysis (from the other side) in Stage 3 — applied a third time. See [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md).

---

## 5. Folder Architecture

```text
05-analysis/
│
├── README.md                          ← you are here
│
├── 01-overview.md                     ← What the Analysis module is (and isn't)
├── 02-domain.md                       ← The Prediction entity, Verdict, Evidence
├── 03-processing-pipeline.md          ← Poll → Claim → Queue → ML call → Store → Status write-back
├── 04-ml-client-contract.md           ← The exact Backend ↔ ML Engine request/response shape
├── 05-cross-module-boundaries.md      ← Analysis ↔ Observation (consumer), Analysis ↔ Alert (deferred)
├── 06-authorization.md                ← Who can see a Prediction, and where that's actually enforced
├── 07-api-contract.md                 ← How this module completes GET /observations/{id}
├── 08-implementation-roadmap.md       ← Build order, Sprint 4 breakdown
│
├── adr/
│   ├── ADR-001-polling-worker-not-push.md
│   ├── ADR-002-no-dedicated-evidence-table.md
│   └── ADR-003-ml-failure-retry-then-fail.md
│
└── diagrams/
    ├── prediction-pipeline-sequence.svg
    └── ml-failure-retry-state.svg
```

---

## 6. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What the Analysis module owns, and the one rule it never breaks |
| 2 | [`02-domain.md`](./02-domain.md) | The Prediction entity, Verdict, why Evidence has no table of its own |
| 3 | [`03-processing-pipeline.md`](./03-processing-pipeline.md) | The exact Poll → Claim → Queue → ML → Store flow |
| 4 | [`04-ml-client-contract.md`](./04-ml-client-contract.md) | The Backend↔ML Engine request/response shape, and failure handling |
| 5 | [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) | How Analysis consumes Observation's exposed contracts, and what it exposes for Alert |
| 6 | [`06-authorization.md`](./06-authorization.md) | Who can ever see a Prediction (spoiler: enforced entirely by Observation's own gate) |
| 7 | [`07-api-contract.md`](./07-api-contract.md) | How this module finally makes `GET /observations/{id}`'s `prediction` field real |
| 8 | [`08-implementation-roadmap.md`](./08-implementation-roadmap.md) | Build order inside Sprint 4, mapped to Layers |
| — | [`adr/`](./adr) | The three pivotal decisions for this module |
| — | [`diagrams/`](./diagrams) | Pipeline sequence diagram, ML-failure retry state diagram |

---

## 7. What This Folder Deliberately Does NOT Redefine

```text
ML Contract request/response fields         → docs.zip/05-integration/02-ML_CONTRACT.md
predictions table schema                    → 01-database/schema/entities.md §6
Verdict / AnalysisStatus enum values        → 01-database/schema/enums.md
Alert Generation Policy (who decides)        → docs.zip/07-adrs/ADR-011-Alert-Generation-Policy.md
Error response shape                        → docs.zip/09-api-reference/07-ERROR_CODES.md
Layer structure (API→…→Presentation)         → backend-architecture/06-implementation-layers.md
Observation's exposed write/read contracts   → docs/backend/observation/05-cross-module-boundaries.md
                                                 (this folder only consumes what's already declared there)
```

---

## 8. Design Status

```text
Analysis Module Design
████████████████████████████ 100% (ready for implementation)

Overview                    ✅ Frozen
Domain                      ✅ Frozen
Processing Pipeline         ✅ Frozen
ML Client Contract          ✅ Frozen
Cross-Module Boundaries     ✅ Frozen
Authorization               ✅ Frozen
API Contract                ✅ Frozen
Implementation Roadmap      ✅ Frozen
```

> As with the three folders before it, once this folder is used to generate code, it becomes frozen too.
