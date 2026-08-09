# 05 — Cross-Module Boundaries

> Applies the same pattern already established twice — [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md) and [`docs/backend/observation/05-cross-module-boundaries.md`](../04-observation/05-cross-module-boundaries.md) — to this module's two real cross-module interactions. Third occurrence, same shape.

---

## 1. Analysis → Observation: Consuming What Stage 3 Already Exposed

```text
Direction check: Analysis depends on Observation (allowed, per 05-module-dependencies.md §6)
```

Everything this module needs from Observation was deliberately built in Stage 3, ahead of time, specifically for this moment:

```text
Observation\Application\Contracts\ObservationLookupContract
    └── findByIdForOrganization(observationId, organizationId): Observation | null
          (used by the Queue Worker to fetch the full raw_ases_json before calling ML —
           see 03-processing-pipeline.md §3)

Observation\Infrastructure\ObservationRepository
    ├── markProcessing(observationId)                      ← called by the Poller
    ├── markCompleted(observationId, processedAt)            ← called by the Queue Worker, success path
    └── markFailed(observationId, processedAt)                 ← called by the Queue Worker, failure path
```

**This module never runs its own query against the `observations` table.** Every read and every status write goes through one of the four methods above — all four already existed, unconsumed, since Stage 3, exactly as `docs/backend/observation/08-implementation-roadmap.md` §5's exit checklist anticipated: *"exposed now, called by nobody yet — Analysis will call them in Stage 4."*

---

## 2. Alert → Analysis: What This Module Must Expose Now, for Stage 5

```text
Direction check: Alert (Stage 5) will depend on Analysis (allowed, per 05-module-dependencies.md §7)
Analysis does NOT depend on Alert, and never will.
```

Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §12: *"Who Is Allowed to Create an Alert? ... Analysis → Alert. Because an Alert is a result of analysis, not a result of receiving an Observation."* Read together with [`ADR-011-Alert-Generation-Policy`](../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md) — *"The ML Engine returns only Predictions. The Backend evaluates those Predictions against platform policies and decides whether an Alert should be created"* — the module actually responsible for evaluating policy and creating the Alert row is the **Alert module itself**, reading Analysis's output, exactly mirroring how Analysis reads Observation's output.

**This module's obligation in Stage 4:** expose a narrow, read-only surface analogous to `ObservationLookupContract`, ready for Alert to consume once Stage 5 begins — even though nothing calls it yet:

```text
Analysis\Application\Contracts\PredictionLookupContract
    └── findByObservationId(observationId): Prediction | null
          (read-only — no write methods; Alert will use this, plus its own polling
           mechanism for "which Predictions haven't been evaluated for an Alert yet" —
           that polling design is Stage 5's own concern, not specified here)
```

**What the Analysis module is never allowed to do:** call anything resembling `CreateAlertAction`, import anything from an eventual Alert module namespace, or write to an `alerts` table. If a future engineer's instinct, while finishing `markCompleted`, is *"since I already know the risk_score is 95, let me just create the Alert right here"* — that is exactly the forbidden reverse direction, and exactly the kind of shortcut this documentation series exists to prevent, three modules in a row now.

---

## 3. Summary Table

| Interaction | Direction | Allowed? | Mechanism |
|-------------|-----------|----------|-----------|
| Analysis reads Observation's `raw_ases_json` | Analysis → Observation | ✅ Allowed | `ObservationLookupContract` (Observation-owned, read-only) |
| Analysis writes `observations.analysis_status` | Analysis → Observation | ✅ Allowed | `ObservationRepository::markProcessing/Completed/Failed()` (Observation-owned) |
| Analysis calls the ML Engine | Analysis → ML Engine (external) | ✅ Allowed | `MLClient` (this module's own Infrastructure layer) |
| Alert (Stage 5) reads Prediction data | Alert → Analysis | ✅ Allowed (future) | `PredictionLookupContract` (Analysis-owned, read-only, exposed now) |
| Analysis creates an Alert | Analysis → Alert | ❌ Never | N/A — no such call exists, or ever will, inside this module |
| Analysis reads/writes `alerts` table | Analysis → Alert | ❌ Never | N/A |

---

## 4. Why This Consistently-Applied Pattern Matters, a Third Time

Same payoff, restated once more because it's the whole point of the exercise: if the ML Engine is replaced, if Alert's policy logic becomes vastly more sophisticated in V2, or if Predictions ever need to be recomputed by a different model — none of that requires touching Observation's code, and none of it requires Analysis to touch Alert's code either. Each module's contribution to the pipeline is a stable, narrow, one-directional interface — never a direct reach into a neighboring module's internals.
