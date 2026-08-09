# ADR-002: `risk_summary` Is Grouped by Prediction `verdict`, Not Alert `severity`

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Dashboard Module |
| **Affects** | `PredictionStatsContract`, the `risk_summary` field in `GET /dashboard`'s response |

---

## Context

[`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8 names this aggregate **"Risk Summary"** as a distinct item from **"Alert Summary"** — two separate bullet points, not one. Two plausible data sources exist: `predictions.verdict` (`SAFE`/`SUSPICIOUS`/`MALICIOUS`, produced for *every* analyzed Observation) or `alerts.severity` (`LOW`/`MEDIUM`/`HIGH`/`CRITICAL`, which only exists for the subset of Predictions that actually produced an Alert). Choosing wrong would either duplicate the Alert Summary aggregate under a different name, or silently exclude every `SAFE`-verdict Observation from what's meant to represent overall platform risk.

---

## Decision

`risk_summary` is sourced from `predictions.verdict`, via a new `PredictionStatsContract::verdictDistributionForOrganization()` method owned by the Analysis module — covering **every** analyzed Observation, including `SAFE` ones.

---

## Rationale

### Why verdict instead of severity?
Because `alerts.severity` only exists for Predictions that already cleared the "is this even alert-worthy" bar (`verdict != SAFE`, per [`docs/backend/alert/02-domain.md`](../../06-alert/02-domain.md) §4) — grouping by severity would silently omit every `SAFE` Prediction from the summary entirely, making "Risk Summary" actually mean "severity of the subset of things that became Alerts," which is a materially different, narrower statement than what a Dashboard's overall risk posture indicator should communicate. A summary genuinely meant to answer *"what does our overall Observation traffic look like, risk-wise"* needs the full distribution, `SAFE` included, precisely so a `SAFE`-heavy Organization's dashboard visibly reflects that its Agents are behaving normally — not just a raw Alert count with no denominator.

### Why is this a separate contract from `AlertSummaryContract`, given both modules ultimately relate to "risk"?
Because [`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §8 itself lists them as two separate bullet points, and because of ownership: `verdict` is Analysis's own field (per [`docs/backend/analysis/02-domain.md`](../../05-analysis/02-domain.md) §1), `severity` is Alert's own field (per [`docs/backend/alert/02-domain.md`](../../06-alert/02-domain.md) §1). Each contract stays owned by whichever module actually produces that specific piece of data — mixing them into one contract would blur an ownership boundary this entire series has been careful to keep sharp.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Group `risk_summary` by `alerts.severity` instead | Silently excludes every `SAFE` Prediction, understating the true denominator of analyzed traffic and conflating this aggregate with Alert Summary in substance if not in name |
| Merge `risk_summary` and `AlertSummaryContract`'s status counts into a single combined contract | Blurs the Analysis/Alert ownership boundary for no real benefit — Dashboard already composes multiple contracts in one response; there's no cost to keeping them as two separate, clearly-owned sources |
| Compute `risk_summary` from a `COUNT` over `observations.analysis_status = 'COMPLETED'` joined to `predictions` inside the Dashboard module itself | Violates `01-overview.md` §4's golden rule — Dashboard must never query another module's tables directly, even for a read-only aggregate; the query must live inside Analysis's own contract implementation |

---

## Consequences

- ✅ `risk_summary` genuinely represents the full population of analyzed Observations, `SAFE` included — an accurate "overall risk posture" indicator, not a disguised re-statement of Alert counts.
- ✅ Ownership stays clean: Analysis owns `verdict`-based aggregation, Alert owns `severity`/`status`-based aggregation, exactly matching each module's own data.
- ⚠️ The Dashboard frontend receiving both `risk_summary` (by verdict) and `organization_stats.open_alerts` (by Alert status) must be built with the understanding that these are two related but distinct numbers — not accidentally treated as interchangeable in the UI.
