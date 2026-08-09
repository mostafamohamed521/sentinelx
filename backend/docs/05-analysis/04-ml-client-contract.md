# 04 — ML Client Contract

> Implements exactly [`ML_CONTRACT.md`](../docs/docs/05-integration/02-ML_CONTRACT.md), [`ADR-014-Stable-ML-Contract`](../docs/docs/07-adrs/ADR-014-Stable-ML-Contract.md), and [`ADR-007-Versioned-Contracts`](../docs/docs/07-adrs/ADR-007-Versioned-Contracts.md). Nothing here invents a field the frozen contract doesn't already name.

---

## 1. What the Backend Sends

Per [`ML_CONTRACT.md`](../docs/docs/05-integration/02-ML_CONTRACT.md) §"Request":

```text
- Observation   (the full ASES payload — context, events, metadata, exactly as stored)
- Analysis Options
```

```json
{
  "observation": {
    "id": "0198c3a1-...",
    "context": { "...": "..." },
    "events": [ "..." ],
    "metadata": { "...": "..." }
  },
  "analysis_options": {}
}
```

**`observation` is `raw_ases_json`, sent unmodified** — the same document Observation validated and stored in Stage 3, fetched via `ObservationLookupContract`. This module never re-shapes it before forwarding.

**`analysis_options` is currently sent as an empty object.** The frozen `ML_CONTRACT.md` names this field but does not yet define its contents — no ADR specifies what options exist. Implementing this module means sending `{}` (or omitting fields not yet specified) rather than inventing option keys that aren't documented anywhere. If/when a real option is needed (e.g., a specific model version to target), that requires its own ADR before this module's ML Client changes — consistent with [`ADR-014-Stable-ML-Contract`](../docs/docs/07-adrs/ADR-014-Stable-ML-Contract.md): *"Any incompatible change requires a new contract version rather than modifying the existing one."*

---

## 2. What the Backend Receives

Per [`ML_CONTRACT.md`](../docs/docs/05-integration/02-ML_CONTRACT.md) §"Response":

```text
- Verdict
- Risk Score
- Confidence
- Summary
- Reasons
- Evidence
```

```json
{
  "verdict": "SUSPICIOUS",
  "risk_score": 62,
  "confidence": 0.78,
  "summary": "Unusual outbound network call following a file read outside the expected working directory.",
  "model_version": "sentinelx-ml-1.4.2",
  "reasons": [ "..." ],
  "evidence": [ "..." ]
}
```

**`verdict` is genuinely produced as all three documented values.** As of the integration audit's CONTRACT-003/DATAFLOW-003 resolution, the ML Service's own `risk_score`→`verdict` thresholds (see `ml/docs/ADR-ml-001-verdict-thresholds.md` — an explicitly flagged engineering default, not a frozen business rule) actually emit `SAFE`, `SUSPICIOUS`, and `MALICIOUS`, not just accept them structurally on the Backend side. Previously this document listed the vocabulary the Backend accepts without confirming the ML Service ever produced the full set.

**Mapping to the `predictions` table** (per [`02-domain.md`](./02-domain.md) §1 and §4):

```text
response.verdict          → predictions.verdict          (copied verbatim)
response.risk_score        → predictions.risk_score        (copied verbatim)
response.confidence         → predictions.confidence         (copied verbatim)
response.summary              → predictions.summary              (copied verbatim)
response.model_version          → predictions.model_version         (copied verbatim)
entire response body               → predictions.prediction_json      (stored whole, unmodified)
```

`model_version` is required on the `predictions` table (NOT NULL) — if the ML Engine's response ever omits it, that is treated as a contract violation (see §4) and this analysis attempt fails, exactly like any other malformed response.

---

## 3. Transport

Per [`ML_CONTRACT.md`](../docs/docs/05-integration/02-ML_CONTRACT.md), the ML Engine is a FastAPI service (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §6). This module's `MLClient` (Infrastructure layer) is a plain HTTP client:

```text
POST {ML_SERVICE_URL}/analyze     (exact path is an ML Engine implementation detail,
                                      configured, not hardcoded, in this module)
Content-Type: application/json
Authorization: Bearer <ML_SERVICE_TOKEN>
Body: { observation, analysis_options }
```

**Authentication is credential-enforced, not merely network-topology-trusted.** No authentication scheme for this internal Backend↔ML call was specified in any frozen document at the time this module was first built — per [`SECURITY_MODEL.md`](../docs/docs/05-integration/03-SECURITY_MODEL.md), only Human (JWT) and Agent (API Key) authentication were defined there. The integration audit's SECURITY-004 finding made this gap explicit: relying solely on network topology (`DEPLOYMENT_ARCHITECTURE.md`'s "Isolated ML execution") is not verifiable or testable from this repository. This is now resolved with a genuine, if intentionally simple, decision: a required shared bearer token (`ML_SERVICE_TOKEN`, configured identically on both sides). `MLClient` fails loudly (`MLConfigurationException`) at first use if unset, rather than silently omitting the header; the ML Service itself rejects any `/analyze` request whose `Authorization` header doesn't match. This is a shared-secret scheme appropriate for an internal service boundary — not a full OAuth/JWT scheme, which would be disproportionate here.

---

## 4. Contract Violations vs. Genuine Failures — Both Treated as Failures Here

```text
Genuine failure:      network timeout, 5xx from the ML Engine, connection refused
Contract violation:    200 OK but missing a required field (e.g., no verdict, or
                          verdict outside SAFE|SUSPICIOUS|MALICIOUS)
```

**Both are treated identically by this module: the Observation is marked `FAILED`, no Prediction is written.** A contract violation is not "close enough, store what we got" — per [`02-domain.md`](./02-domain.md) §6, invariant 3, every one of the five promoted fields must be genuinely present and valid, or nothing is stored at all. Distinguishing the two failure kinds in logs/monitoring (for operators to tell "ML is down" from "ML sent something we don't understand") is valuable and expected, but doesn't change the outcome for the `observations`/`predictions` tables.

---

## 5. Retry Behavior

See [`adr/ADR-003-ml-failure-retry-then-fail.md`](./adr/ADR-003-ml-failure-retry-then-fail.md) for the full reasoning — no frozen document specifies exact retry counts or backoff timing, so this module adopts Laravel's own standard queue-job retry mechanism (a small, fixed number of attempts with backoff) as a sensible engineering default, clearly flagged as an implementation choice rather than a documented business rule:

```text
AnalyzeObservationJob
    tries = 3        (default; adjust freely — not a frozen business rule)
    backoff = [10s, 60s, 300s]   (exponential-ish; adjust freely)

After all retries exhausted → markFailed(), job ends, no exception escapes to crash the worker
```

---

## 6. Summary

```text
ML Client Contract

Send      → raw_ases_json + analysis_options ({} for now), unmodified
Receive    → verdict, risk_score, confidence, summary, model_version, reasons, evidence
Store       → 5 fields promoted to columns, full response kept in prediction_json
Failure      → contract violations and transport failures both → FAILED, no Prediction row
Retries       → a small number of attempts, then FAILED — an engineering default, not a
                 documented business rule
```
