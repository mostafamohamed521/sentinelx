# 05 — Cross-Module Boundaries

> Applies the exact same pattern already established in [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md) to this module's two real cross-module interactions. If that file made sense, this one will too — it's the same shape, twice.

---

## 1. Observation → Agent: the `touchLastSeen` Write-Back

This is the reverse case of the one documented in [`docs/backend/agent/02-domain.md`](../03-agent/02-domain.md) §3, now seen from the writer's side.

```text
Direction check: Observation depends on Agent (allowed, per 05-module-dependencies.md §5)
```

```text
ReceiveObservationAction (Observation module)
    │
    ├── 1. INSERT INTO observations (...)
    │
    └── 2. calls Agent\Infrastructure\AgentRepository::touchLastSeen(agentId, receivedAt)
              — the ONE legal write this module ever makes outside its own table,
                already declared as legitimate in docs/backend/agent/02-domain.md §3
```

**Rules:**
```text
✔ Both writes happen inside the same database transaction — if the Observation insert
  fails, last_seen_at must not be touched either.
✘ The Observation module never runs its own `UPDATE agents SET last_seen_at = ...`
  query directly — it always goes through the Agent module's own repository method,
  the exact same discipline already established for Authentication → Agent in Stage 2.
```

---

## 2. Analysis → Observation: `analysis_status` Transitions (Not Built in This Stage)

```text
Direction check: Analysis depends on Observation (allowed, per 05-module-dependencies.md §6)
```

Symmetric to §1, but the roles are reversed and the callee is this module:

```text
Analysis module (Stage 4, not built yet)
    │
    ├── polls or is queued for observations WHERE analysis_status = 'PENDING'
    │     (query already indexed and reserved for this exact purpose —
    │      see 01-database/schema/indexes.md, Index 5: "The Worker — the single
    │      most important query in the entire backend")
    │
    └── writes back via Observation\Infrastructure\ObservationRepository's own
        exposed methods, e.g.:
            markProcessing(observationId)
            markCompleted(observationId, processedAt)
            markFailed(observationId, processedAt)
```

**This module's obligation in Stage 3:** expose these three narrow, explicit write methods on its own Repository, even though nothing calls them yet. This is intentionally built now, ahead of Stage 4, for the same reason `AgentLookupContract` was built in Stage 2 ahead of Stage 3 actually consuming it — the *interface* is part of this module's own contract surface, and defining it here keeps Stage 4 from ever needing to reach directly into the `observations` table.

**What the Observation module must never do:** call *out* to Analysis. There is no `ObservationController` or `ReceiveObservationAction` code path that invokes anything ML-related, queues anything, or knows a Worker exists. The direction is strictly Analysis-reaches-into-Observation via the exposed Repository methods above — never the reverse.

---

## 3. Observation ↔ Analysis: `GET /observations/{id}` Composition, Deferred

Full reasoning in [`adr/ADR-003-prediction-composition-deferred.md`](./adr/ADR-003-prediction-composition-deferred.md). Summary:

```text
Stage 3 (this module):  GET /observations/{id} returns Observation fields only.
                          "prediction": null, always, for every Observation — there is
                          no Prediction module yet to have an opinion.

Stage 4 (Analysis):      GET /observations/{id} is extended — implemented as Analysis
                           depending on Observation (allowed direction) to fetch the base
                           Observation via a read-only ObservationLookupContract (exposed
                           by this module, exactly like AgentLookupContract), then
                           attaching its own Prediction data to build the full response.
```

**This module's obligation in Stage 3:** expose a read-only `ObservationLookupContract` (parallel to `AgentLookupContract`) now, even though nothing outside this module calls it yet — so that when Analysis ships in Stage 4, it consumes an existing, stable interface rather than requiring a change to this module's internals.

```text
Observation\Application\Contracts\ObservationLookupContract
    └── findByIdForOrganization(observationId, organizationId): Observation | null
          (read-only — no write methods)
```

---

## 4. Summary Table

| Interaction | Direction | Allowed? | Mechanism |
|-------------|-----------|----------|-----------|
| Observation writes `agents.last_seen_at` | Observation → Agent | ✅ Allowed | `AgentRepository::touchLastSeen()` (Agent-owned method) |
| Observation reads Agent identity at ingestion | Observation → Agent | ✅ Allowed (via `AuthenticatedIdentity`, not a fresh query) | Handed off by Authentication middleware |
| Analysis writes `observations.analysis_status` | Analysis → Observation | ✅ Allowed (Stage 4) | `ObservationRepository::markProcessing/Completed/Failed()` (Observation-owned methods) |
| Analysis reads Observation for composition | Analysis → Observation | ✅ Allowed (Stage 4) | `ObservationLookupContract` (Observation-owned, read-only) |
| Observation calls into Analysis/ML in any way | Observation → Analysis | ❌ Never | N/A — no such call exists anywhere in this module |
| Observation reads `predictions` or `alerts` tables | Observation → Analysis/Alert | ❌ Never | N/A |

---

## 5. Why This Consistently-Applied Pattern Matters

Exactly the payoff already stated in `docs/backend/agent/04-api-key-coordination.md` §6, restated for this module: if the ML Engine is replaced entirely, or Analysis becomes a fully separate microservice in some future version, **zero code inside the Observation module changes.** Its two exposed contracts (`AgentRepository::touchLastSeen`, already consumed; `ObservationRepository`'s status-writers and `ObservationLookupContract`, not yet consumed) remain exactly as they are — only the caller on the other side changes.
