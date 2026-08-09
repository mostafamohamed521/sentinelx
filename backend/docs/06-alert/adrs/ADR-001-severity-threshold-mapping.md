# ADR-001: Severity Is Derived From `risk_score` Via Fixed, Even Thresholds (Engineering Default)

| | |
|---|---|
| **Status** | ✅ Accepted (Engineering Default — Resolves a Genuine Gap in Frozen Documentation) |
| **Scope** | Alert Module |
| **Affects** | `SeverityMapper`, every Alert's `severity` field |

---

## Context

`alerts.severity` (`LOW`/`MEDIUM`/`HIGH`/`CRITICAL`) is frozen in [`01-database/schema/enums.md`](../../01-database/01-database/schema/enums.md) §8, which explains *why* it exists separately from the numeric `risk_score` (*"users think in colors/levels, not raw numbers, when making an operational decision"*) but does not specify the numeric boundaries between the four levels. No other frozen document — not `ML_CONTRACT.md`, not `ADR-011-Alert-Generation-Policy`, not any ADR in the `07-adrs/` set — defines this mapping either. [`ADR-011-Alert-Generation-Policy`](../../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md) explicitly anticipates that thresholds may become Organization-configurable in a future version, confirming this is a real, not-yet-specified policy surface rather than an oversight this ADR is contradicting.

---

## Decision

```text
risk_score  0 – 24   → LOW
risk_score 25 – 49    → MEDIUM
risk_score 50 – 74     → HIGH
risk_score 75 – 100      → CRITICAL
```

Implemented as a single, isolated `SeverityMapper::fromRiskScore(int): Severity` function in the Alert module's Domain layer.

---

## Rationale

### Why four equal-width bands rather than some other distribution?
With no frozen guidance and no production data yet to calibrate against, four evenly-sized 25-point bands is the simplest, most defensible starting point — easy to explain to any Human reading a Dashboard, and trivially adjustable once real Prediction data reveals whether risk scores cluster in a way that would make uneven bands more useful (e.g., if most real `MALICIOUS` verdicts cluster around 60-70, a future revision might narrow the `HIGH`/`CRITICAL` boundary).

### Why implement this as an isolated function rather than inline conditionals wherever severity is needed?
Because [`ADR-011-Alert-Generation-Policy`](../../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md) already signals this exact value is likely to change — either as better calibration data arrives, or as a genuine V2 feature (per-Organization configurable thresholds). A single function is the one place that would need to change; scattering `if risk_score >= 75` checks across Actions, Resources, or listeners would turn a one-line future change into a multi-file hunt.

### Why flag this as an "Engineering Default" rather than presenting it as equivalent to the rest of this documentation series' frozen decisions?
Because it genuinely is different in kind — every other ADR in this series (module boundaries, event-vs-poll mechanisms, response codes) traces directly back to an already-frozen document. This one does not; it fills a real gap the frozen documentation set left open. Being honest about that distinction matters more than appearing equally authoritative on both kinds of decisions.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Derive severity from `verdict` alone (e.g., `SUSPICIOUS` → always `MEDIUM`, `MALICIOUS` → always `HIGH`) | Discards the numeric granularity `risk_score` exists specifically to provide — `enums.md` §8 explicitly frames Severity as a translation of the *numeric* score, not the coarse 3-value verdict |
| Leave `severity` unset / nullable until a real policy is designed | `severity` is `NOT NULL` per the already-frozen `constraints.md` §7 — every Alert must have one at creation time; deferring this decision isn't structurally possible without contradicting the frozen schema |
| Make thresholds configurable via environment variables from day one | No product requirement (Organization-level configuration, per ADR-011, is explicitly future-scoped) calls for this in V1; adds configuration surface with no current consumer |

---

## Consequences

- ✅ Every Alert has a deterministic, well-defined severity from the moment it's created.
- ✅ The single-function design means recalibrating thresholds — or making them configurable later — is a small, contained change.
- ⚠️ These specific numbers are a best-guess default, not validated against real production risk-score distributions — expect this ADR to be revisited once real usage data exists, without that revision requiring a change to this module's architecture, only its constants.
