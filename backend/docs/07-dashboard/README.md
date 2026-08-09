# SentinelX — Dashboard Module Documentation

> **Status:** 🟢 **Active Design — Stage 6 of the Implementation Order**
> **Depends on (Frozen):** `docs.zip` (Documentation Baseline v2.0), `docs/backend/database/`, `docs/backend/backend-architecture/`
> **Also builds on:** `docs/backend/agent/`, `docs/backend/observation/`, `docs/backend/analysis/`, `docs/backend/alert/` (all Baseline v1.0) — Dashboard is the first module that depends on all four at once
> **Owner:** Backend Architecture Team
> **Extends, never conflicts with:** the frozen root documentation. Every rule here is a direct implementation of an already-frozen decision — most importantly [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8 and [`DASHBOARD_API.md`](../docs/docs/09-api-reference/06-DASHBOARD_API.md).
>
> **One scope-narrowing decision is made explicit here, up front, because it changes what "Sprint 6" actually means to build:** see §4 below.

---

## 1. Why does this folder exist?

Same reason the five folders before it exist. Not a tutorial — the **Source of Truth** an engineer (human or Claude Code) reads before writing a single line of the Dashboard module.

> **If there is ever a conflict between this folder and `docs.zip`, `docs.zip` wins.**

---

## 2. Where This Sits in the Roadmap

```text
Stage 0 — Foundation                     ✅ Done
Stage 1 — Organization + Authentication  ✅ Done
Stage 2 — Agent + API Key submodule      ✅ Done
Stage 3 — Observation Pipeline           ✅ Done
Stage 4 — Analysis (ML Integration)      ✅ Done
Stage 5 — Alert Engine                   ✅ Done
Stage 6 — Dashboard                      🟢 THIS FOLDER
Stage 7 — Audit & Settings               ⏳ Next
```

Per [`backend-architecture/08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §8, **Definition of Done for this Sprint: "a User sees real data on the Dashboard."**

---

## 3. The Core Idea in One Sentence

> **The Dashboard module owns absolutely nothing — it exists purely to ask four other modules, each through its own narrow read contract, "what's the current state of your data," and hand back one combined snapshot.**

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8: *"Different from every other module — it owns no data. This is a rule, not an oversight."*

---

## 4. Scope-Narrowing Decision, Stated Up Front

[`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §8 lists this Sprint's work as `Overview → Observation History → Alert History → Search → Filters`, which reads like five separate deliverables. **It is one.** `Observation History` and `Alert History` are the already-existing, already-built `GET /observations` and `GET /alerts` list endpoints from Stage 3 and Stage 5. `Search` and `Filters` are the already-existing query parameters on those same two endpoints (`agent_id`, `analysis_status`, `status`, `severity`). Nothing about any of that is new work for this Sprint. The **only** new backend deliverable this Sprint adds is the single aggregation endpoint [`DASHBOARD_API.md`](../docs/docs/09-api-reference/06-DASHBOARD_API.md) actually defines: `GET /api/v1/dashboard`. See [`adr/ADR-001-dashboard-scope-is-single-aggregation-endpoint.md`](./adr/ADR-001-dashboard-scope-is-single-aggregation-endpoint.md) for the full reasoning — read it before assuming this Sprint needs five feature branches.

---

## 5. Module Boundary Recap (from the Frozen Baseline)

```text
Dashboard Module                              The Four Modules It Reads From
────────────────────                          ───────────────────────────────────────────────
✔ Nothing of its own — no table, no entity      Agent        → exposes AgentSummaryContract (NEW this Sprint)
✔ One composition endpoint (GET /dashboard)      Observation → exposes ObservationSummaryContract (NEW)
                                                   Analysis    → exposes PredictionStatsContract (NEW)
✘ Does NOT own Observation Summary                Alert       → extends AlertSummaryContract (already exists,
✘ Does NOT own Alert Summary                                     from Stage 5 — adds one method)
✘ Does NOT own Agent Summary
✘ Does NOT own Risk Summary
```

Dependency direction (per [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §10, unchanged here):

```text
Dashboard
    │
    ├────────► Observation
    ├────────► Analysis
    ├────────► Alert
    └────────► Agent
```

**Dashboard depends on all four. None of the four depend on Dashboard, and none ever will.** This is the fifth occurrence of the same one-directional-contract pattern — see [`04-aggregation-contracts.md`](./04-aggregation-contracts.md) for exactly how it plays out across four modules simultaneously, for the first time.

---

## 6. Folder Architecture

```text
07-dashboard/
│
├── README.md                              ← you are here
│
├── 01-overview.md                         ← What the Dashboard module is (and isn't)
├── 02-domain.md                           ← Why there's no entity — the response is a computed DTO
├── 03-scope-resolution.md                 ← The full reasoning behind §4 above
├── 04-aggregation-contracts.md            ← The four contracts this Sprint needs, one per source module
├── 05-authorization.md                    ← Who can view the Dashboard
├── 06-api-contract.md                     ← The one endpoint, in full
├── 07-implementation-roadmap.md           ← Build order, Sprint 6 breakdown
│
├── adr/
│   ├── ADR-001-dashboard-scope-is-single-aggregation-endpoint.md
│   ├── ADR-002-risk-summary-by-verdict-not-severity.md
│   └── ADR-003-all-roles-can-view-dashboard.md
│
└── diagrams/
    ├── dashboard-dependency-fanout.svg
    └── dashboard-aggregation-sequence.svg
```

---

## 7. Recommended Reading Order

| # | File | What you'll learn |
|---|------|--------------------|
| 1 | [`01-overview.md`](./01-overview.md) | What the Dashboard module owns (nothing) and the one rule it never breaks |
| 2 | [`02-domain.md`](./02-domain.md) | Why there's no migration, no model, no entity for this module at all |
| 3 | [`03-scope-resolution.md`](./03-scope-resolution.md) | Why this Sprint is one endpoint, not five features |
| 4 | [`04-aggregation-contracts.md`](./04-aggregation-contracts.md) | The four read contracts this module consumes, and the three small additive changes they require elsewhere |
| 5 | [`05-authorization.md`](./05-authorization.md) | Who can view the Dashboard (spoiler: everyone, same reasoning as Alert) |
| 6 | [`06-api-contract.md`](./06-api-contract.md) | The exact response shape of `GET /dashboard` |
| 7 | [`07-implementation-roadmap.md`](./07-implementation-roadmap.md) | Build order inside Sprint 6, mapped to Layers |
| — | [`adr/`](./adr) | The three pivotal decisions for this module |
| — | [`diagrams/`](./diagrams) | Dependency fan-out diagram, aggregation sequence diagram |

---

## 8. What This Folder Deliberately Does NOT Redefine

```text
GET /observations, GET /alerts (list, filters)  → already fully built, Stage 3 / Stage 5
Error response shape                            → docs.zip/09-api-reference/07-ERROR_CODES.md
Layer structure (API→…→Presentation)             → backend-architecture/06-implementation-layers.md
Agent/Observation/Analysis/Alert's own domain     → their own respective folders — this module
  rules, entities, and existing contracts             never restates them, only consumes them
```

---

## 9. Design Status

```text
Dashboard Module Design
████████████████████████████ 100% (ready for implementation)

Overview                    ✅ Frozen
Domain (no entity)          ✅ Frozen
Scope Resolution            ✅ Frozen
Aggregation Contracts       ✅ Frozen
Authorization               ✅ Frozen
API Contract                ✅ Frozen
Implementation Roadmap      ✅ Frozen
```

> As with the five folders before it, once this folder is used to generate code, it becomes frozen too.
