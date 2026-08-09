# 03 — Scope Resolution: Why This Sprint Is One Endpoint, Not Five

> Full reasoning behind the claim made in [`README.md`](./README.md) §4. Worth its own file because getting this wrong means either under-building (missing a real requirement) or over-building (recreating endpoints that already exist, badly, a second time).

---

## 1. The Apparent Five-Item List

[`backend-architecture/08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §8 describes Sprint 6 as:

```text
Overview
    ↓
Observation History
    ↓
Alert History
    ↓
Search
    ↓
Filters
```

Read in isolation, this looks like five distinct features to build. It isn't — and the frozen, specific API contract confirms this directly.

---

## 2. Checking Each Item Against What Already Exists

```text
"Overview"              → the ONE genuinely new thing: GET /dashboard
                            (per DASHBOARD_API.md — organization stats, recent alerts,
                            recent observations, risk distribution, active agents)

"Observation History"    → GET /api/v1/observations, already fully built in Stage 3
                             (docs/backend/observation/06-api-contract.md §2)
                             — paginated, most-recent-first, nothing missing

"Alert History"           → GET /api/v1/alerts, already fully built in Stage 5
                              (docs/backend/alert/06-api-contract.md §1)
                              — paginated, nothing missing

"Search"                   → No search endpoint exists in ANY frozen API reference
                               document — not OBSERVATIONS_API.md, not ALERTS_API.md,
                               not DASHBOARD_API.md. No frozen document specifies what
                               "search" would even mean here (full-text? by Agent name?
                               by date range?) — there is nothing concrete to build,
                               only a word in a roadmap outline

"Filters"                   → agent_id / analysis_status on GET /observations (Stage 3),
                                status / severity on GET /alerts (Stage 5) — already
                                built, already documented, already tested
```

---

## 3. The Decision

**Sprint 6's only new backend deliverable is `GET /api/v1/dashboard`.** "Observation History," "Alert History," and "Filters" require zero new code — they were already delivered in Stages 3 and 5 and simply become *reachable* from the Dashboard frontend once this Sprint ships the aggregation endpoint that anchors the UI. "Search" is not built, because no frozen document defines what it would search, over which fields, or with what semantics — building it now would mean inventing an unspecified feature from a single unadorned word in a high-level roadmap outline.

---

## 4. Why This Matters Enough to Write Down

Without this resolution, a natural reading of the Sprint roadmap could lead to:

```text
✘ A redundant, slightly-different second implementation of Observation/Alert listing,
  now living inside the Dashboard module — creating two sources of truth for the same
  data, and directly contradicting Dashboard's own "owns no data" rule from
  01-overview.md §4
✘ A speculative, unspecified full-text search feature bolted onto Observations or
  Alerts, with made-up semantics no product requirement actually calls for
```

Both outcomes would violate this documentation series' own core discipline — every prior module's ADRs exist to prevent exactly this kind of scope drift from an ambiguous prose description. This file applies that same discipline to the roadmap document itself, not just to individual technical decisions.

---

## 5. If "Search" Is Ever a Real Requirement

Should a genuine search requirement emerge later (e.g., "search Observations by a keyword inside `raw_ases_json`"), it belongs as a new, explicitly-specified feature inside the **Observation module itself** (the owner of that data), documented with its own API contract addition and, if it needs anything beyond a simple `LIKE`/`ILIKE` query, its own ADR (e.g., justifying a PostgreSQL full-text index) — never as a Dashboard-owned feature, which would violate [`01-overview.md`](./01-overview.md) §4's golden rule all over again.
