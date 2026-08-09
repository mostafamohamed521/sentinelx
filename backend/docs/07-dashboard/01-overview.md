# 01 — Dashboard Module Overview

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8. Nothing here contradicts them.

---

## 1. What Is the Dashboard, In One Sentence?

> **The Dashboard is a single, read-only snapshot assembled at request time from four other modules' own data — it is a view, not a place where anything is stored.**

Per [`DASHBOARD_API.md`](../docs/docs/09-api-reference/06-DASHBOARD_API.md): *"The Dashboard endpoint aggregates the most commonly requested operational data into a single response. This minimizes frontend requests and improves dashboard loading performance."*

---

## 2. What the Dashboard Module Is Responsible For

```text
Call four narrow, read-only contracts (Agent, Observation, Analysis, Alert)
Assemble their results into one JSON response
Return that response, scoped to the caller's Organization
```

That's the complete list. Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8, this module's own classification is simply **"Read Layer."**

## 3. What the Dashboard Module Is Explicitly NOT Responsible For

```text
✘ Storing anything — there is no dashboard_snapshots table, no cache table, nothing
✘ Deciding what an Alert's severity is, what a Prediction's verdict is, or whether an
  Agent is "healthy" — every one of those judgments was already made by the module
  that owns that data; Dashboard only reads and displays it
✘ Filtering or searching Observations/Alerts in some new way — that already exists on
  their own list endpoints (see 03-scope-resolution.md)
✘ Real-time push updates (websockets, SSE, polling intervals) — not specified in any
  frozen document for V1; this is a plain request/response endpoint
```

---

## 4. The One Rule This Module Never Breaks

> **The Dashboard module never computes a business judgment itself — every number, every list item, every summary it returns was already computed and stored by the module that actually owns that data.**

If a future engineer's instinct is *"let me just run a quick `COUNT(*) FROM alerts WHERE severity = 'CRITICAL'` directly in the Dashboard's own query, it's faster than going through a contract"* — that instinct is exactly the mistake [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §11 exists to prevent: *"Ownership Is More Important Than Access... `Analysis` never modifies `Observation` — except through a clearly defined interface. This is one of the most important rules of a Modular Monolith."* The same discipline applies here, in the read direction: Dashboard never queries another module's tables directly, even for something as simple as a `COUNT`.

---

## 5. Why Dashboard Is Its Own Module and Not Folded Into Each Source Module

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8's own framing, Dashboard is *"different from every other module"* specifically because composing across module boundaries is its entire reason for existing — no single source module (Agent, Observation, Analysis, Alert) could sensibly own "the combined view of all four" without becoming aware of, and dependent on, the other three, which would immediately violate [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §12: *"Lower Modules Never Know Higher Modules."* Dashboard is the one module explicitly positioned *above* all four specifically so composition has a legitimate home.

---

## 6. Route Ownership vs. Module Ownership

Unlike every module before it, Dashboard owns exactly one route, and that route requires no ownership negotiation with anyone — because Dashboard sits at the top of the dependency chain, it is always the permitted caller, never the module being deferred to:

| Route | Implemented By |
|-------|------------------|
| `GET /dashboard` | **Dashboard module** — no deferred composition, no route-grouping ambiguity like Stage 3/4's `GET /observations/{id}` had; Dashboard is allowed to depend on everything it needs |

---

## 7. Session Summary

```text
Dashboard Module — Overview

Owns
✘ Nothing — no table, no migration, no Eloquent model

Responsible For
✔ One aggregation endpoint, composing four modules' own read contracts

Golden Rule
✔ Every value in the response was computed by the module that actually owns that
  data — Dashboard never queries another module's tables directly, and never makes
  its own business judgment about what a number means.
```
