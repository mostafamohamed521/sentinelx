# 07 — API Contract (Completing Observation's Route)

> This module owns no route of its own. This file documents exactly how it completes the one field left as `null` in Stage 3 — per [`docs/backend/observation/adr/ADR-003-prediction-composition-deferred.md`](../04-observation/adr/ADR-003-prediction-composition-deferred.md), which promised this exact moment.

---

## 1. `GET /api/v1/observations/{observationId}` — Now Fully Realized

**Route owner:** Observation module (unchanged) · **Prediction composition:** Analysis module (new, as of this Sprint) · **Auth:** JWT (Human) · **Role:** Owner, Admin, Member

**Response `200 OK` — Observation whose analysis has completed:**
```json
{
  "data": {
    "id": "0198c3a1-...",
    "agent_id": "0198a1b2-...",
    "organization_id": "0198a0f0-...",
    "analysis_status": "COMPLETED",
    "raw_ases_json": { "...": "..." },
    "received_at": "2026-07-29T10:00:01Z",
    "processing_started_at": "2026-07-29T10:00:05Z",
    "processed_at": "2026-07-29T10:00:09Z",
    "created_at": "2026-07-29T10:00:01Z",
    "updated_at": "2026-07-29T10:00:09Z",
    "prediction": {
      "id": "0198c4b2-...",
      "verdict": "SUSPICIOUS",
      "confidence": 0.78,
      "risk_score": 62,
      "summary": "Unusual outbound network call following a file read outside the expected working directory.",
      "model_version": "sentinelx-ml-1.4.2",
      "analyzed_at": "2026-07-29T10:00:09Z"
    }
  }
}
```

**Response `200 OK` — Observation still pending, processing, or failed:**
```json
{
  "data": {
    "id": "0198c3a1-...",
    "analysis_status": "PENDING",
    "...": "...",
    "prediction": null
  }
}
```

**`prediction` is `null` whenever `analysis_status` is anything other than `COMPLETED`.** This includes `FAILED` — a failed analysis attempt never produces a Prediction (see [`02-domain.md`](./02-domain.md) §2), so `null` correctly represents that state too, not just "not yet analyzed."

**Note on `prediction_json`:** the embedded `prediction` object above deliberately mirrors the Agent/Observation modules' own list-vs-detail pattern — it exposes the five promoted columns (`verdict`, `confidence`, `risk_score`, `summary`, `model_version`, `analyzed_at`) but **not** the full `prediction_json` blob (Evidence, Reasons, Models, etc.) in this summary-style embedding. Whether and how the full `prediction_json` (Evidence detail) is ever exposed to the Dashboard is a Stage 6 (Dashboard) design question, not decided here — this module makes the raw data available via `PredictionLookupContract`, but doesn't presume how a future consumer will choose to surface it.

---

## 2. Implementation Note — Composition, Not a New Controller

Per [`docs/backend/observation/adr/ADR-003-prediction-composition-deferred.md`](../04-observation/adr/ADR-003-prediction-composition-deferred.md), the route's `Controller@show` action, still physically inside the Observation module's API layer, now composes across two sources instead of one:

```text
ObservationController@show
    │
    ├── 1. $observation = GetObservationAction (Observation module — unchanged from Stage 3)
    │
    ├── 2. $prediction = Analysis\Application\Contracts\PredictionLookupContract
    │         ::findByObservationId($observation->id)     ← the ONE new line, calling
    │                                                          Analysis's exposed contract
    │
    └── 3. return ObservationResource($observation, $prediction)
```

**This is the only place in the entire codebase where the Observation module's own Controller code changes as a result of Stage 4 shipping** — and even that change is a single, additive call to a pre-declared, stable interface, not a rewrite of anything Stage 3 already built. Everything else about `GetObservationAction`, `ObservationController`, and the rest of Observation's Stage-3 implementation is untouched.

---

## 3. Errors

Unchanged from [`docs/backend/observation/07-api-contract.md`](../04-observation/07-api-contract.md) §3 — this module introduces no new error case for this endpoint. A missing/cross-tenant Observation still returns `404` from the Observation module's own existing check, before Analysis's composition step is ever reached.
