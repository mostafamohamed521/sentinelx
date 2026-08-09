# ADR-002: Audit Logs Cover Human-Initiated Administrative Actions Only, Never Observation/Prediction Volume

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Resolves a Genuine Gap) |
| **Scope** | Audit Module |
| **Affects** | Which domain events the Audit module's listeners subscribe to |

---

## Context

No frozen document specifies which actions are "audit-worthy." Left unscoped, a naive reading of *"Recording events"* ([`04-module-responsibilities.md`](../../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §9) could plausibly include every Observation submission (potentially thousands per day per Organization, per [`01-database/schema/indexes.md`](../../01-database/01-database/schema/indexes.md)'s own framing of Observation volume) and every Prediction stored — which would make `audit_logs` grow at roughly the same rate as `observations` and `predictions` combined, for data that already has its own complete, immutable historical record in those exact tables.

---

## Decision

Audit Logs cover only Human-initiated, mutating, administratively-significant actions — the exhaustive list in [`03-audit-logging.md`](../03-audit-logging.md) §3. Observation submission and Prediction storage are explicitly excluded.

---

## Rationale

### Isn't excluding Observations from "Audit" a contradiction, given `predictions.prediction_json` is itself described as becoming "part of the platform's audit history" in `entities.md` §6?
No — that phrase describes a *property* of the Prediction record (it's permanent, queryable historical evidence), not a claim that Prediction storage needs a *second*, duplicate record inside a different table called `audit_logs`. The Observation and Prediction tables already *are* SentinelX's audit history for security events — that's their entire purpose. The `Audit` module (Stage 7) serves a different, narrower purpose: an accountability trail of *administrative* actions taken by *Humans* — who created this Agent, who rotated this key, who resolved this Alert — which is exactly the kind of data no other table in the schema captures at all.

### Why exclude Alert *creation* (system-generated) but include Alert *acknowledge/resolve* (Human-initiated)?
Because the distinguishing line this ADR draws is "does a specific, identifiable Human choose to do this" — Alert creation is entirely automatic (Stage 5's `EvaluateAlertPolicyOnPredictionStored` listener, no Human involved, already timestamped via the Alert's own `created_at`), while acknowledging and resolving are deliberate Human actions with real accountability value (*"who decided this incident was handled, and when"*).

### What's the actual cost of getting this scope wrong in either direction?
Under-scoping (missing a genuinely important administrative action) is a low-cost mistake to fix later — adding one more event dispatch and listener is a small, additive, non-breaking change, exactly like every other small addition in this series. Over-scoping (including Observation/Prediction volume) would be a much more expensive mistake to walk back — it would mean a fast-growing table with no real accountability value, likely requiring a retention/cleanup strategy no frozen document currently anticipates, and degraded query performance on the one table meant to answer "who did what" quickly.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Audit every Observation submission and Prediction storage, in addition to administrative actions | Massive, unjustified duplication of data already fully recorded, immutably, in `observations` and `predictions`; no accountability value added (the "actor" is always the same Agent, already known) |
| Audit nothing from Agent/Alert/API Key modules, only Organization/User actions | Under-serves the module's own frozen responsibility — Agent creation and API Key rotation are exactly the kind of administratively-significant, Human-initiated actions an audit trail exists to capture |
| Make the audited-action list configurable per-Organization | No product requirement calls for this; adds configuration surface with no current consumer, mirroring the same reasoning already applied to Alert's severity thresholds (ADR-001) staying fixed in V1 |

---

## Consequences

- ✅ `audit_logs` grows at a rate proportional to genuine administrative activity, not raw platform traffic — keeping it fast to query and genuinely useful for its intended purpose.
- ✅ No duplication of data that already has a complete, immutable home elsewhere.
- ⚠️ If a future requirement emerges to audit system-initiated events too (e.g., "show me every time the ML Engine was unreachable"), that's a different concern — likely served by [`MONITORING_STRATEGY.md`](../../docs/docs/10-operational-architecture/05-MONITORING_STRATEGY.md)'s own operational metrics, not by expanding this table's scope; revisit this ADR explicitly if that's ever genuinely needed.
