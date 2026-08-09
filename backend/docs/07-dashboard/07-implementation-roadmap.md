# 07 — Dashboard Module Implementation Roadmap

> Converts everything designed in this folder into a build plan, following the exact same rule set as the previous five modules and the Layer order from [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md).

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     ✅ Done
Sprint 3 — Observation Pipeline ✅ Done
Sprint 4 — ML Integration       ✅ Done
Sprint 5 — Alert Engine         ✅ Done
Sprint 6 — Dashboard            🟢 This roadmap
```

**Definition of Done for Sprint 6** (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §8): **"a User sees real data on the Dashboard."**

---

## 2. Build Order (Layer by Layer)

```text
1. Domain
   └── DashboardSnapshot DTO (02-domain.md §2) — a plain, non-persisted value object

2. Infrastructure (in FOUR other modules, not Dashboard's own — see §3 below)
   ├── Agent: AgentSummaryContract + implementation
   ├── Observation: ObservationSummaryContract + implementation
   ├── Analysis: PredictionStatsContract + implementation
   └── Alert: AlertSummaryContract extended with listRecentForOrganization()

3. Application (Dashboard's own)
   └── GetDashboardSnapshotAction
         — calls all four contracts, assembles DashboardSnapshot; no business logic
           of its own beyond assembly (see 01-overview.md §4's golden rule)

4. Presentation
   └── DashboardResource — maps DashboardSnapshot to the exact JSON shape in
        06-api-contract.md §1

5. API
   ├── DashboardController@show → GetDashboardSnapshotAction
   └── Route: GET /dashboard, auth:jwt, no per-Role restriction (05-authorization.md)

6. Tests, for every layer above — see the 5 categories below
```

---

## 3. The Three Small, Additive Changes Required Elsewhere

Per [`04-aggregation-contracts.md`](./04-aggregation-contracts.md), this Sprint's real work is mostly *not* inside the Dashboard module itself:

```text
Agent module        → add AgentSummaryContract (new interface + implementation)
Observation module   → add ObservationSummaryContract (new interface + implementation,
                          likely a thin wrapper over the existing listForOrganization())
Analysis module        → add PredictionStatsContract (new interface + implementation,
                            one grouped-COUNT query)
Alert module              → add ONE new method to the EXISTING AlertSummaryContract
                              (listRecentForOrganization) — no new interface needed here
```

**None of these four changes touch any existing file's business logic** — each is a new, additive read-only method or interface, following the exact discipline already demonstrated in Sprint 4 (`claimNextPendingBatch` added to Observation) and Sprint 5 (`PredictionStored` event and `findById` added to Analysis). Call this out explicitly in your own summary if you implement this Sprint — it is expected, not scope creep, but it's still real, cross-module work that shouldn't be missed by assuming "Dashboard Sprint" means "only touch the Dashboard folder."

---

## 4. What Sprint 6 Explicitly Does NOT Build

```text
✘ A search endpoint of any kind (see 03-scope-resolution.md §5)
✘ A caching layer for GET /dashboard (see 02-domain.md §3)
✘ Real-time updates (websockets, SSE, polling) for the Dashboard
✘ Any new filter parameters on GET /observations or GET /alerts — those already exist
✘ A "risk-per-agent" or any other derived metric not explicitly named in
  DASHBOARD_API.md's five documented sections
```

---

## 5. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ GET /dashboard returns all five sections, correctly populated, for an Organization
     with real Agents, Observations, Predictions, and Alerts
   ✔ organization_stats.total_agents matches the actual count for that Organization
   ✔ risk_summary correctly reflects the verdict distribution across all Predictions

2. Edge Case
   ✔ A brand-new Organization with zero Agents/Observations/Alerts returns a
     well-formed response with all zeros/empty arrays, not an error
   ✔ An Organization with fewer than 5 recent items in any list returns exactly that
     many, not padded or erroring
   ✔ risk_summary correctly returns 0 for a verdict that has never occurred yet
     (e.g., no MALICIOUS Predictions exist yet for this Organization)

3. Business Rule
   ✔ Every value in the response can be traced to exactly one of the four contracts —
     assert this by mocking each contract independently and confirming the response
     field changes only when its corresponding contract's return value changes
   ✔ organization_id is passed identically to all four contract calls, derived once
     from the AuthenticatedIdentity

4. Authorization
   ✔ Owner, Admin, and Member each independently succeed (explicit symmetry test,
     same discipline as Sprint 5's Role symmetry tests)
   ✔ An Agent (API Key) attempting this endpoint → 401 (wrong guard)
   ✔ Unauthenticated request → 401

5. Data Isolation
   ✔ Organization A's dashboard never includes any data belonging to Organization B —
     test this explicitly by seeding two Organizations with distinct data and
     asserting each dashboard reflects only its own
```

---

## 6. Sprint 6 Exit Checklist

```text
☐ No new migration — this Sprint adds zero tables
☐ AgentSummaryContract implemented in the Agent module
☐ ObservationSummaryContract implemented in the Observation module
☐ PredictionStatsContract implemented in the Analysis module
☐ AlertSummaryContract extended with listRecentForOrganization() in the Alert module
☐ GetDashboardSnapshotAction implemented, composing all four, with no direct
  cross-module table queries anywhere
☐ GET /dashboard implemented and passing tests, for all three Roles
☐ A real, end-to-end demo works: log in as a User with real data across all four
  prior modules, hit GET /dashboard, see an accurate combined snapshot
☐ docs/backend/dashboard/ (this folder) marked Frozen once code matches it exactly
```
