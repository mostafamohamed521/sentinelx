# 03 — Alert Generation Pipeline

> Implements [`ADR-011-Alert-Generation-Policy`](../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md): *"The Backend evaluates those Predictions against platform policies and decides whether an Alert should be created."* Reuses the event-based cross-module notification pattern already established twice before — [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md) §4 (`AgentArchived`) and now here for the third time.

---

## 1. Why Event-Driven, Not Polling (Unlike Analysis's Own Pipeline)

Stage 4's Analysis module polls for `PENDING` Observations because [`01-database/schema/indexes.md`](../01-database/01-database/schema/indexes.md) explicitly reserves an index for exactly that query. **No equivalent index exists for "Predictions not yet evaluated for an Alert"** — and for good reason: `predictions` has no status column analogous to `observations.analysis_status` to poll against. Building a poll-based design here would mean either an expensive anti-join (`predictions LEFT JOIN alerts ... WHERE alerts.id IS NULL`) scanned repeatedly with no supporting index, or inventing a new column/flag no frozen document calls for.

Instead, this module reuses the domain-event pattern: the module that just did the relevant write (Analysis, having just stored a Prediction) announces the fact, and Alert — which is *allowed* to depend on Analysis — listens.

---

## 2. The One Small, Additive Change Required in Analysis (Stage 4)

Exactly analogous to Sprint 4's own `claimNextPendingBatch` addition to Observation (see [`docs/backend/analysis/08-implementation-roadmap.md`](../05-analysis/08-implementation-roadmap.md) §3), this Sprint requires one small addition to Analysis's existing `AnalyzeObservationAction`:

```text
Analysis\Application\AnalyzeObservationAction
    │
    ├── (existing, Stage 4) INSERT INTO predictions
    ├── (existing, Stage 4) ObservationRepository::markCompleted(...)
    │
    └── (NEW, this Sprint) dispatch PredictionStored(predictionId, observationId, organizationId)
          — a plain domain event. Analysis has zero knowledge of who's listening,
            exactly like Agent's ArchiveAgentAction dispatching AgentArchived.
```

**This is the only line of code added to the Analysis module in this entire Sprint.** Everything else lives inside the Alert module.

---

## 3. The Listener and Policy Evaluation

```text
Alert\Listeners\EvaluateAlertPolicyOnPredictionStored
    │
    │ subscribes to PredictionStored (Analysis has no reference to this class —
    │   the Alert module registers itself as a listener, the standard Laravel
    │   event-listener wiring, not a direct call from Analysis)
    │
    ├── 1. fetch the Prediction via Analysis\Application\Contracts\
    │       PredictionLookupContract::findByObservationId() — the exact contract
    │       Stage 4 already exposed, unconsumed until now
    │
    ├── 2. if verdict == SAFE → stop, no Alert (see 02-domain.md §4)
    │
    ├── 3. idempotency guard: if an Alert already exists for this prediction_id
    │       (UNIQUE constraint would reject a duplicate anyway, but check first to
    │       avoid a needless failed INSERT) → stop
    │
    ├── 4. severity = SeverityMapper::fromRiskScore(prediction.risk_score)
    │       (see 02-domain.md §3 — ADR-001's thresholds)
    │
    └── 5. INSERT INTO alerts (prediction_id, severity, status: OPEN)
```

---

## 4. Why `OPEN → RESOLVED` Directly Is Allowed (Skip-Ahead)

No frozen document requires acknowledging before resolving. A Human who immediately recognizes a `LOW` severity Alert as a known, already-handled false positive should be able to resolve it in one action rather than being forced through a ceremonial acknowledge step first. This is a deliberate, minor design choice — flagged here rather than silently assumed — consistent with the project's own stated principle (per the Engineering Workflow reference material): *"the Simple Solution is better than the Complex Solution, unless the Complex one is actually justified."* Requiring acknowledge-before-resolve would be the more complex rule, with no documented business need driving it.

---

## 5. Failure Handling

```text
PredictionLookupContract returns null
  (should not happen — the event only fires after a successful Prediction write —
   but defensively handled)
    → log and stop; no Alert created; no exception escapes to crash the listener

Duplicate Alert attempt (UNIQUE constraint violation, race between the idempotency
check and the INSERT)
    → caught, treated as "Alert already exists," not surfaced as an error anywhere
```

Unlike Analysis's `AnalyzeObservationJob` (Stage 4), this listener has no external network call to retry against — its only dependency is the same database the Prediction was just written to, so transient-failure handling is far simpler here: a normal Laravel event listener failure is logged and does not need the multi-attempt backoff policy Stage 4 required for the ML Engine call.

---

## 6. Full Sequence

See the rendered version at [`diagrams/alert-generation-sequence.svg`](./diagrams/alert-generation-sequence.svg).

```text
Analysis Module              Event Bus            Alert Module           Database
      │                          │                     │                    │
      │── INSERT Prediction ─────────────────────────────────────────────►│
      │── markCompleted() ───────────────────────────────────────────────►│
      │── dispatch(PredictionStored) ─►│                                    │
      │                          │── notify ──────────►│                    │
      │                          │                     │── fetch Prediction►│
      │                          │                     │   (via contract)   │
      │                          │                     │── verdict != SAFE? │
      │                          │                     │── map severity     │
      │                          │                     │── INSERT Alert ───►│
```

---

## 7. Summary

```text
Alert Generation Pipeline

Trigger    → PredictionStored event, dispatched by Analysis (one new line there)
Discovery   → event listener, not polling — no index exists to poll against
Policy       → verdict != SAFE → create; severity via fixed risk_score thresholds (ADR-001)
Idempotency   → checked before insert; UNIQUE constraint as the final guarantee
Failure        → logged, non-retrying (no external call involved), never crashes silently
```
