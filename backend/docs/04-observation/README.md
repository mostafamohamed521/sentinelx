# SentinelX — Observation Module Documentation

> **Status:** 🟢 **Active Design — Stage 3 of the Implementation Order**
> **Depends on (Frozen):** `docs.zip` (Documentation Baseline v2.0), `docs/backend/database/`, `docs/backend/authentication/`, `docs/backend/backend-architecture/`
> **Also builds on:** `docs/backend/agent/` (Baseline v1.0) — Stage 3 cannot exist without Stage 2
> **Owner:** Backend Architecture Team
> **Extends, never conflicts with:** the frozen root documentation. Every rule here is a direct, faithful implementation of an already-frozen decision — most importantly [`ADR-001-Canonical-Observation`](../docs/docs/07-adrs/ADR-001-Canonical-Observation.md), [`ADR-004-Immutable-Observation-Storage`](../docs/docs/07-adrs/ADR-004-Immutable-Observation-Storage.md), and [`ADR-006-Backend-as-Orchestrator`](../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md).

---

## 1. Why does this folder exist?

Same reason `02-auth` and `03-agent` exist. Not a tutorial, not an explanation of code that already exists — the **Source of Truth an engineer (human or Claude Code) reads before writing a single line of the Observation module.**

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      ✅ Done (docs/backend/agent/)
Stage 3 — Observation Pipeline           🟢 THIS FOLDER
Stage 4 — Analysis                       ⏳ Next
Stage 5 — Alert                          ⏳
Stage 6 — Dashboard                      ⏳
Stage 7 — Audit & Settings               ⏳
```

Per [`backend-architecture/07-implementation-order.md`](../00-backend_architecture/00-backend_architecture/07-implementation-order.md) §6:

> "The real beginning of SentinelX's core... No ML yet — deliberately. The first thing that must be confirmed is: can the SDK actually send an Observation? Does storage work? Is the JSON Schema correct?"

**Definition of Done for Stage 3:** `SDK → POST /observations → Validation → Database, with zero mocks.`

---

## 3. The Core Idea in One Sentence

> **The Observation module's entire job is to prove an Observation is well-formed and belongs to a real, active Agent, then persist it exactly as received. Its responsibility ends the instant that row exists in the database.**

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §5:

> "Responsibility Ends At: Stored Successfully. After that, its role is complete... Does NOT Know About: ML, Risk Score, Alerts. The single most important point in this session."

---

## 4. Module Boundary Recap (from the Frozen Baseline)

```text
Observation Module                    Analysis Module (Stage 4, does not exist yet)
────────────────────                  ───────────────────────────────────────────────
✔ Observation (entity)                ✔ Prediction (entity)
✔ raw_ases_json                       ✔ Evidence
✔ analysis_status = PENDING (only)    ✔ analysis_status transitions onward
                                          (PROCESSING / COMPLETED / FAILED)

✘ Does NOT know ML exists             ✘ Does NOT own Observation
✘ Does NOT know Analysis exists       ✔ Depends on Observation (allowed direction)
✘ Does NOT know Alert exists
```

Dependency direction (per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md), unchanged here):

```text
Observation
    ↓
Agent
    ↓
Organization

Analysis
    ↓
Observation
```

**Observation depends on Agent. Analysis depends on Observation. Observation does not depend on Analysis, and never will.** This is why this folder never describes "trigger ML analysis" as part of the Observation module's own responsibility — see [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md).

---

## 5. Folder Architecture

```text
04-observation/
│
├── README.md                            ← you are here
│
├── 01-overview.md                       ← What the Observation module is (and isn't)
├── 02-domain.md                         ← The Observation entity, ASES, immutability
├── 03-ingestion-pipeline.md             ← Receive → Authenticate → Validate → Persist
├── 04-validation.md                     ← What "valid ASES" means, and what it deliberately doesn't mean
├── 05-cross-module-boundaries.md        ← Observation ↔ Agent, Observation ↔ Analysis, the two write-back contracts
├── 06-authorization.md                  ← Who can submit, who can view
├── 07-api-contract.md                   ← Full endpoint-by-endpoint contract
├── 08-implementation-roadmap.md         ← Build order, Sprint 3 breakdown
│
├── adr/
│   ├── ADR-001-async-ingestion-202-accepted.md
│   ├── ADR-002-structural-validation-only.md
│   └── ADR-003-prediction-composition-deferred.md
│
└── diagrams/
    ├── observation-analysis-status-state.svg
    └── observation-ingestion-sequence.svg
```

---

## 6. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What the Observation module owns, and the one rule it never breaks |
| 2 | [`02-domain.md`](./02-domain.md) | The Observation entity, what ASES is, why it's immutable and never decomposed |
| 3 | [`03-ingestion-pipeline.md`](./03-ingestion-pipeline.md) | The exact Receive → Authenticate → Validate → Persist flow, and why the response is `202`, not `201` |
| 4 | [`04-validation.md`](./04-validation.md) | The precise, narrow scope of "validation" this module performs |
| 5 | [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) | How Observation writes to Agent (`last_seen_at`) and how Analysis, not Observation, will later attach Predictions |
| 6 | [`06-authorization.md`](./06-authorization.md) | Agent Capability vs. Human Role, applied to Observations specifically |
| 7 | [`07-api-contract.md`](./07-api-contract.md) | Every endpoint, request/response shape, status codes, error cases |
| 8 | [`08-implementation-roadmap.md`](./08-implementation-roadmap.md) | Build order inside Sprint 3, mapped to Layers |
| — | [`adr/`](./adr) | The three pivotal decisions for this module, with rejected alternatives |
| — | [`diagrams/`](./diagrams) | Analysis-status state diagram, ingestion sequence diagram |

---

## 7. What This Folder Deliberately Does NOT Redefine

```text
ASES structure (Context/Events/Metadata)   → docs.zip/03-specifications/01-ASES_SPECIFICATION.md
Canonical Event Dictionary                 → docs.zip/03-specifications/02-EVENT_DICTIONARY.md
ASES JSON Schema field list                → docs.zip/03-specifications/03-ASES_JSON_SCHEMA.md
observations table schema                  → 01-database/schema/entities.md §5
observations indexes                       → 01-database/schema/indexes.md
API Key verification mechanics             → 02-auth/05-api-keys.md, contracts/api-key-format.md
Roles (Owner / Admin / Member)              → 02-auth/06-authorization.md (Baseline v2.0)
Error response shape                       → docs.zip/09-api-reference/07-ERROR_CODES.md
Pagination shape                           → docs.zip/09-api-reference/08-PAGINATION.md
Layer structure (API→…→Presentation)        → backend-architecture/06-implementation-layers.md
Agent ↔ API Key coordination pattern        → docs/backend/agent/04-api-key-coordination.md (the pattern this
                                                folder reuses for Observation ↔ Agent and Observation ↔ Analysis)
```

---

## 8. Design Status

```text
Observation Module Design
████████████████████████████ 100% (ready for implementation)

Overview                    ✅ Frozen
Domain                      ✅ Frozen
Ingestion Pipeline          ✅ Frozen
Validation Scope            ✅ Frozen
Cross-Module Boundaries     ✅ Frozen
Authorization               ✅ Frozen
API Contract                ✅ Frozen
Implementation Roadmap      ✅ Frozen
```

> As with `02-auth` and `03-agent`, once this folder is used to generate code, it becomes frozen too. Any future change must come from a real business requirement (V2), not a mid-implementation rethink.
