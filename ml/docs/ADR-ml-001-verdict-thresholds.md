# ADR-ml-001: `verdict` Is Derived From `risk_score` Via Two Fixed Thresholds (Engineering Default)

| | |
|---|---|
| **Status** | Accepted (Engineering Default — Resolves a Genuine Gap in Frozen Documentation) |
| **Scope** | ML Service |
| **Affects** | `run_pipeline.py`'s `verdict`/`confidence` derivation, every Prediction's `verdict` field |

---

## Context

The Backend's `Verdict` enum (`backend/app/Modules/Analysis/Domain/Verdict.php:7-9`) accepts exactly `SAFE | SUSPICIOUS | MALICIOUS`, and `backend/docs/05-analysis/04-ml-client-contract.md:94` names the same three values as the ML Service's expected output vocabulary. No frozen document — not `ML_CONTRACT.md`, not any ADR in `docs/07-adrs/` — specifies the numeric `risk_score` boundary between `SUSPICIOUS` and `MALICIOUS`; the ML Service's pipeline previously only ever distinguished two outcomes (`SUSPICIOUS` / `LOW_RISK`), leaving `MALICIOUS` unreachable under any input (integration audit CONTRACT-003/DATAFLOW-003).

---

## Decision

```text
risk_score  0 - 49   -> SAFE          (LOW_RISK, renamed -- the vocabularies already
                                         agreed conceptually here)
risk_score 50 - 84    -> SUSPICIOUS    (the pre-existing boundary, unchanged)
risk_score 85 - 100     -> MALICIOUS     (new -- previously unreachable)
```

Implemented as two named constants, `SAFE_SUSPICIOUS_BOUNDARY = 50` and `SUSPICIOUS_MALICIOUS_BOUNDARY = 85`, in `ml/config.py`.

`confidence` (also previously absent from every response) is derived from the same two boundaries: how far `risk_score` sits from the nearest one. A score sitting exactly on a boundary is the least certain classification a threshold-based rule can make; a score deep inside a band is the most certain. This is a first-pass heuristic, not a calibrated probability — there is no labeled outcome data yet to calibrate a real confidence signal against.

---

## Rationale

### Why 85, specifically?
No frozen document or production data constrains this choice. 85 keeps the existing `SUSPICIOUS` boundary (50) untouched — preserving today's already-tested `SUSPICIOUS` behavior exactly — while reserving a comfortably wide top band (85-100) for `MALICIOUS`, consistent with `MALICIOUS` being reserved for the clearest, highest-confidence cases rather than diluting it down into the same territory as a borderline `SUSPICIOUS` result.

### Why keep 50 as the SAFE/SUSPICIOUS boundary rather than also revisiting it?
It is the one number in this pipeline that already shipped and was already exercised (as the `SUSPICIOUS`/`LOW_RISK` split). Changing it now, at the same time as introducing `MALICIOUS`, would make two behavioral changes indistinguishable from each other in any future incident review. Isolating the actual gap (a missing third tier) to a single new boundary keeps this change minimal and its effect traceable.

### Why implement this as two named constants rather than inline comparisons?
Because, exactly as `SeverityMapper`'s own precedent argues, these are first-pass numbers without production calibration behind them — expect them to move. A `match`/`if`-chain reading two named constants from `config.py` is the one place that needs to change later, not a value re-typed inline wherever a verdict is derived.

### Why flag this as an "Engineering Default" rather than a frozen contract value?
Because it genuinely fills a gap the frozen documentation set left open, the same distinction `SeverityMapper`'s own ADR draws for its risk-score-to-severity bands. Presenting it as equally authoritative to an already-frozen decision would misrepresent how settled it actually is.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Leave `MALICIOUS` permanently unreachable, only ever emit `SAFE`/`SUSPICIOUS` | Contradicts the Backend's own already-frozen three-value enum and the documented contract's own vocabulary — the Backend reserves a state the system could never actually report |
| Derive `MALICIOUS` from a separate signal (e.g. a specific rule match) rather than a `risk_score` threshold | No such signal exists yet in the pipeline's output; would require new pipeline logic beyond this phase's scope of reconciling the *existing* score into the *existing* three-value vocabulary |
| Compute `confidence` as a constant (e.g. always `0.75`) | Explicitly rejected by the resolution plan itself — a hardcoded constant is not a genuine signal and would misrepresent every Prediction as equally certain |

---

## Consequences

- Every Prediction now has a genuine `verdict` value drawn from the Backend's real three-value vocabulary, and a genuine, if first-pass, `confidence` score.
- The two boundaries and the confidence heuristic are a best-guess default, not validated against real production risk-score distributions or labeled outcomes — expect this ADR to be revisited once real usage data exists, without that revision requiring any change beyond these constants and the `compute_confidence` heuristic.
