# 04 — Aggregation Contracts

> Fifth occurrence of the pattern — [`docs/backend/agent/04-api-key-coordination.md`](../03-agent/04-api-key-coordination.md), [`docs/backend/observation/05-cross-module-boundaries.md`](../04-observation/05-cross-module-boundaries.md), [`docs/backend/analysis/05-cross-module-boundaries.md`](../05-analysis/05-cross-module-boundaries.md), [`docs/backend/alert/05-cross-module-boundaries.md`](../06-alert/05-cross-module-boundaries.md), and now this one — but the first time Dashboard needs **four simultaneously**, since it's the first module allowed to depend on all of them at once.

---

## 1. Why Three of These Four Contracts Are New, Not Reused

Every prior "expose a contract now, for later consumption" moment in this series was anticipated by the module being extended, one Sprint ahead of the consumer needing it (`ObservationLookupContract` built in Stage 3, consumed in Stage 4; `PredictionLookupContract` built in Stage 4, consumed in Stage 5). Agent (Stage 2) and Observation (Stage 3) were built *before* this pattern was fully established for forward-looking summary data specifically — their existing contracts (`AgentLookupContract`, `ObservationLookupContract`) are single-record lookups, built for a different consumer (Authentication, Analysis) with a different need (find one specific record). Dashboard needs **aggregate, multi-record** data none of the existing contracts provide. This Sprint adds three new contracts and extends one existing one.

---

## 2. Agent → `AgentSummaryContract` (NEW)

```text
Direction check: Dashboard depends on Agent (allowed, per 05-module-dependencies.md §10)
```

```text
Agent\Application\Contracts\AgentSummaryContract
    ├── countTotalForOrganization(organizationId): int
    ├── countActiveForOrganization(organizationId): int
    └── listRecentlyActiveForOrganization(organizationId, limit): Agent[]
          (ordered by last_seen_at DESC — reuses the exact column already established
           in docs/backend/agent/02-domain.md §3, written by Observation)
```

**This is a small, additive change to the Agent module** — no existing Agent file is rewritten; this is a new Infrastructure-layer implementation of a new interface, exactly the same shape as every other contract added retroactively in this series.

---

## 3. Observation → `ObservationSummaryContract` (NEW)

```text
Direction check: Dashboard depends on Observation (allowed, per 05-module-dependencies.md §10)
```

```text
Observation\Application\Contracts\ObservationSummaryContract
    ├── countForOrganizationSince(organizationId, since: DateTime): int
    └── listRecentForOrganization(organizationId, limit): Observation[]
          (ordered by received_at DESC — reuses ObservationRepository's existing
           listForOrganization() internally, just without pagination metadata)
```

**Also small and additive** — internally, this can simply be a thin wrapper around the `ObservationRepository::listForOrganization()` method that already exists (Stage 3), rather than genuinely new query logic.

---

## 4. Analysis → `PredictionStatsContract` (NEW)

```text
Direction check: Dashboard depends on Analysis (allowed, per 05-module-dependencies.md §10)
```

```text
Analysis\Application\Contracts\PredictionStatsContract
    └── verdictDistributionForOrganization(organizationId): { SAFE: int, SUSPICIOUS: int,
          MALICIOUS: int }
          (a single grouped COUNT query: SELECT verdict, COUNT(*) FROM predictions
           JOIN observations ON ... WHERE observations.organization_id = ? GROUP BY verdict)
```

This is the source of the response's `risk_summary` field — see [`adr/ADR-002-risk-summary-by-verdict-not-severity.md`](./adr/ADR-002-risk-summary-by-verdict-not-severity.md) for why this is grouped by `verdict` (Analysis's own field) rather than `severity` (Alert's field), even though [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8 calls this aggregate "Risk Summary," not "Alert Summary."

---

## 5. Alert → `AlertSummaryContract` (EXTENDING an existing contract)

```text
Direction check: Dashboard depends on Alert (allowed, per 05-module-dependencies.md §10)
```

[`docs/backend/alert/05-cross-module-boundaries.md`](../06-alert/05-cross-module-boundaries.md) §3 already built `countByStatusForOrganization()` in Stage 5, specifically anticipating this moment. This Sprint adds one more method to the same, already-existing interface:

```text
Alert\Application\Contracts\AlertSummaryContract
    ├── countByStatusForOrganization(organizationId): { OPEN: n, ACKNOWLEDGED: n,
    │     RESOLVED: n }                                        ← already existed (Stage 5)
    └── listRecentForOrganization(organizationId, limit): Alert[]   ← NEW this Sprint
          (ordered by created_at DESC)
```

**This is the one contract in this Sprint that required no brand-new interface** — only a new method on something already frozen, which is exactly the payoff [`docs/backend/alert/05-cross-module-boundaries.md`](../06-alert/05-cross-module-boundaries.md) §5 promised when it said the pattern *"compounds rather than repeats identically."*

---

## 6. What Dashboard Is Never Allowed to Do

```text
✘ Query `agents`, `observations`, `predictions`, or `alerts` directly, in any form —
  every single value in the response comes from one of the four contracts above
✘ Import anything from Agent's, Observation's, Analysis's, or Alert's Infrastructure
  or Domain layers — only their exposed Application-layer contracts
✘ Combine two contracts' results into a NEW derived business number the source
  modules didn't already compute (e.g., no "risk-per-agent" ratio invented here —
  if that's ever wanted, it belongs as a new contract method on whichever module
  should own that calculation, decided explicitly, not improvised inside Dashboard)
```

---

## 7. Summary Table

| Contract | Owning Module | Status This Sprint |
|----------|----------------|----------------------|
| `AgentSummaryContract` | Agent | **New** |
| `ObservationSummaryContract` | Observation | **New** |
| `PredictionStatsContract` | Analysis | **New** |
| `AlertSummaryContract` | Alert | **Extended** (one new method on an existing interface) |

---

## 8. Why Four Contracts in One Sprint Is Still the Same Pattern, Not a New One

Each individual relationship here — Dashboard reading one module's exposed, read-only surface — is structurally identical to every prior cross-module boundary in this series. What's new is only that Dashboard is the first module allowed to hold four such relationships simultaneously, which is precisely what [`05-module-dependencies.md`](../00-backend_architecture/00-backend_architecture/05-module-dependencies.md) §10's dependency diagram already shows: Dashboard sits at the top, fanning out to everything beneath it, never the reverse.
