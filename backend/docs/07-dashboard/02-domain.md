# 02 — Dashboard Domain (Or: Why There Isn't One)

> Every prior module in this series opened its `02-domain.md` with a table schema. This one can't — and that absence is itself the design, not a gap.

---

## 1. No Migration, No Table, No Model

Every other module's Domain/Infrastructure layer starts from a `CREATE TABLE`. Dashboard has none, anywhere, in the frozen [`01-database/schema/entities.md`](../01-database/01-database/schema/entities.md) — there is no `dashboard_snapshots`, no `dashboard_cache`, nothing. This is not an oversight this documentation needs to fill in; it's the direct, literal consequence of [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8's own words: *"it owns no data. This is a rule, not an oversight."*

---

## 2. What Replaces an Entity: a Computed Response DTO

Instead of a persisted entity, this module's Domain layer defines a single, plain **Data Transfer Object** — a shape that exists only in memory, for the duration of one request, assembled from four other modules' contract calls:

```text
DashboardSnapshot (DTO — not persisted, not an Eloquent model)
──────────────────────────
organization_stats     { total_agents, active_agents, total_observations_30d,
                          open_alerts }
active_agents           Agent[]              (small list — see 06-api-contract.md)
recent_observations      Observation[]        (small list)
recent_alerts             Alert[]              (small list)
risk_summary                { SAFE: n, SUSPICIOUS: n, MALICIOUS: n }
```

**This shape is a Presentation-layer concept, not a Domain-layer business entity.** It has no invariants of its own beyond "each field is populated from exactly one source contract" — see [`04-aggregation-contracts.md`](./04-aggregation-contracts.md) for which contract fills which field.

---

## 3. Why No Caching Layer Is Specified Here

[`DASHBOARD_API.md`](../docs/docs/09-api-reference/06-DASHBOARD_API.md) motivates the endpoint's existence partly with *"improves dashboard loading performance,"* which might suggest a caching layer belongs in this module's design. **No frozen document specifies a caching mechanism, TTL, or invalidation strategy for this endpoint**, and none is invented here. The performance benefit `DASHBOARD_API.md` describes comes from *request consolidation* (one HTTP round-trip instead of four or five separate ones from the Dashboard frontend) — which this module already delivers simply by existing, with zero caching required. If response-time data from a real deployment later shows this endpoint needs caching, that is a genuine future decision, deserving its own ADR when it's actually needed — not something to speculatively build now.

---

## 4. Domain Invariants (Must Hold True At All Times)

```text
1. Every field in the response is sourced from exactly one owning module's contract —
   never computed by re-deriving a business judgment inside this module.
2. The response is always scoped to the caller's Organization — every underlying
   contract call is passed the same organization_id, derived once from the
   AuthenticatedIdentity, never re-resolved per field.
3. This module writes to no table, ever. If a future requirement needs to persist
   anything (e.g., a saved dashboard layout preference), that persisted concept
   belongs to a different, explicitly-scoped module — not bolted onto this one.
```
