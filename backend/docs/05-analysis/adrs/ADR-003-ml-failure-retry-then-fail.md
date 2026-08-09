# ADR-003: ML Call Failures Are Retried a Small, Fixed Number of Times, Then Marked FAILED

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Not a Documented Business Rule) |
| **Scope** | Analysis Module, `AnalyzeObservationJob` |
| **Affects** | How transient ML Engine failures are handled before an Observation is given up on |

---

## Context

[`ADR-006-Backend-as-Orchestrator`](../../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md) already names the negative consequence explicitly: *"Platform availability depends on successful communication with the ML Engine."* A single transient failure (a momentary network blip, a brief ML Engine restart) should not permanently strand an Observation in `FAILED` if a near-immediate retry would have succeeded. No frozen document specifies exact retry counts, backoff timing, or a maximum wait — this is a genuine gap in the frozen documentation set, not a decision this ADR is overriding.

---

## Decision

`AnalyzeObservationJob` is retried up to **3 times total**, with backoff intervals of **10 seconds, 60 seconds, then 300 seconds** between attempts, using Laravel's own standard queued-job retry mechanism. After the third attempt fails, the Observation is marked `FAILED` (via `ObservationRepository::markFailed`) and no further automatic retry occurs.

**This ADR is explicitly flagged as an engineering default, not a business requirement extracted from frozen documentation.** The specific numbers (3 attempts, this exact backoff schedule) are a reasonable starting point and may be freely adjusted by whoever implements this Sprint, or tuned later based on real ML Engine reliability characteristics — adjusting them does not require a new ADR, unlike the module-boundary decisions elsewhere in this documentation set.

---

## Rationale

### Why 3 attempts specifically, and not more or fewer?
No frozen document specifies a number, so this reflects a standard, conservative default for external-service integration: enough attempts to absorb a brief transient failure, not so many that a genuinely down ML Engine causes Observations to sit `PROCESSING` for an excessive stretch of time while retries slowly exhaust.

### Why exponential-style backoff instead of immediate retry or a fixed interval?
Immediate retry risks hammering an already-struggling ML Engine with no recovery window; a fixed short interval has the same problem at scale (many Observations retrying in lockstep). Growing backoff gives the ML Engine time to recover between attempts, which is standard practice for external-service calls and requires no new business decision to justify.

### Why give up at all, rather than retry indefinitely?
An Observation stuck retrying forever against a genuinely down ML Engine would silently consume Queue Worker capacity indefinitely, and — more importantly — would never surface as an actionable, visible `FAILED` state that an operator (via Monitoring, per [`MONITORING_STRATEGY.md`](../../docs/docs/10-operational-architecture/05-MONITORING_STRATEGY.md)) could notice and investigate. A bounded number of attempts, followed by an explicit terminal state, keeps the system's behavior observable and finite.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Retry indefinitely until success | Risks unbounded resource consumption and hides ML Engine outages from operational visibility |
| No retry at all — fail immediately on the first error | Wastes a large fraction of otherwise-recoverable transient failures, needlessly inflating the `FAILED` count for issues that would have resolved on their own within seconds |
| Retry count/backoff configurable per-Organization or per-Agent | No product requirement calls for this; adds configuration surface with no documented business need |

---

## Consequences

- ✅ Transient ML Engine hiccups are absorbed automatically, without any Observation being prematurely marked `FAILED`.
- ✅ A genuinely down or broken ML Engine surfaces as a bounded, visible batch of `FAILED` Observations within a predictable time window (at most ~6 minutes per Observation, given the backoff schedule above), rather than either failing instantly or hanging forever.
- ⚠️ Because this is an engineering default rather than a frozen business rule, it should be revisited once real production ML Engine reliability data exists — this ADR does not need to be amended to change the numbers, only to change the *policy* (e.g., "retry indefinitely" or "never retry") if that policy itself needs to change.
