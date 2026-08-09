# 05 — Cross-Module Boundaries

> Fourth occurrence of the same pattern — [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md), [`docs/backend/observation/05-cross-module-boundaries.md`](../04-observation/05-cross-module-boundaries.md), [`docs/backend/analysis/05-cross-module-boundaries.md`](../05-analysis/05-cross-module-boundaries.md), and now this one.

---

## 1. Alert → Analysis: Consuming What Stage 4 Already Exposed

```text
Direction check: Alert depends on Analysis (allowed, per 05-module-dependencies.md §7)
```

```text
Analysis\Application\Contracts\PredictionLookupContract
    └── findByObservationId(observationId): Prediction | null
          (already built in Stage 4, unused until now — see docs/backend/analysis/
           05-cross-module-boundaries.md §2)
```

**This module never runs its own query against the `predictions` table.** Every read goes through this one contract method.

---

## 2. Analysis → Alert: the New `PredictionStored` Event

```text
Direction check: this is Analysis notifying, not Analysis depending — a domain event,
                    exactly the mechanism already used for Agent → Authentication in
                    Stage 2 (AgentArchived) and structurally identical here
```

```text
Analysis\Application\AnalyzeObservationAction
    │
    └── dispatches PredictionStored(predictionId, observationId, organizationId)
          — Analysis has zero reference to the Alert module, zero knowledge that
            anything listens, and this line was already anticipated (though not yet
            built) as the natural extension point when Stage 4 was designed
```

```text
Alert\Listeners\EvaluateAlertPolicyOnPredictionStored
    — lives entirely inside the Alert module; registers itself as a listener via
      standard Laravel event-listener wiring; Analysis never imports this class,
      never calls it, never knows it exists
```

**This is the one small, additive change required inside the Analysis module for this Sprint** — see [`03-generation-pipeline.md`](./03-generation-pipeline.md) §2 for the exact placement, and note it is a single `dispatch()` call, not a rewrite of anything Stage 4 already built.

---

## 3. What This Module Exposes for Dashboard (Stage 6, Forward-Looking)

```text
Direction check: Dashboard (Stage 6) will depend on Alert (allowed, per
                    05-module-dependencies.md §8 — "Dashboard → ... Alert")
Alert does NOT depend on Dashboard, and never will.
```

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8: *"Dashboard... Different from every other module — it owns no data... Aggregates (does not own): ...Alert Summary."* This module's obligation, matching the pattern established at every prior stage, is to expose a narrow, read-only surface Dashboard can later consume:

```text
Alert\Application\Contracts\AlertSummaryContract
    └── countByStatusForOrganization(organizationId): { OPEN: n, ACKNOWLEDGED: n, RESOLVED: n }
          (a minimal, read-only aggregate method — exact shape may be refined once
           Stage 6's actual Dashboard requirements are documented; not over-specified
           here, matching how docs/backend/observation/05-cross-module-boundaries.md §3
           deliberately left Stage 4's exact polling design for Stage 4 itself to define)
```

**What this module must never do:** call anything resembling a Dashboard rendering concern, import anything from an eventual Dashboard namespace, or format data specifically for widget display — this module exposes raw counts/records; presentation shaping is Dashboard's job entirely.

---

## 4. Summary Table

| Interaction | Direction | Allowed? | Mechanism |
|-------------|-----------|----------|-----------|
| Alert reads Prediction data | Alert → Analysis | ✅ Allowed | `PredictionLookupContract` (Analysis-owned, read-only) |
| Alert is notified a Prediction was stored | Analysis → Alert (event, not a call) | ✅ Allowed | `PredictionStored` domain event |
| Alert creates an Alert row | — | ✅ Allowed | Entirely within this module — no cross-module call needed to create its own entity |
| Dashboard (Stage 6) reads Alert summary data | Dashboard → Alert | ✅ Allowed (future) | `AlertSummaryContract` (Alert-owned, read-only, exposed now) |
| Alert calls Analysis to create/modify a Prediction | Alert → Analysis | ❌ Never | N/A |
| Alert calls anything in a future Dashboard module | Alert → Dashboard | ❌ Never | N/A |

---

## 5. Why This Pattern, a Fourth Time, Still Matters

The payoff compounds rather than repeats identically: by Stage 5, the *shape* of every cross-module interaction in this entire backend is now uniform — a lower module either exposes a narrow read contract for a higher module to call into, or fires a domain event a higher module may listen for, and never once does a lower module reach upward. An engineer who has read any one of these four `05-cross-module-boundaries.md` files can predict, with confidence, exactly how the next one will be shaped before opening it.
