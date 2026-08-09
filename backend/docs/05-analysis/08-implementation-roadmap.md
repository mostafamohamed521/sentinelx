# 08 — Analysis Module Implementation Roadmap

> Converts everything designed in this folder into a build plan, following the exact same rule set as the previous two modules and the Layer order from [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md).

---

## 1. Where This Sits

```text
Sprint 0 — Foundation           ✅ Done
Sprint 1 — Identity Foundation  ✅ Done
Sprint 2 — Agent Foundation     ✅ Done
Sprint 3 — Observation Pipeline ✅ Done
Sprint 4 — ML Integration       🟢 This roadmap
```

**Definition of Done for Sprint 4** (per [`08-sprint-roadmap.md`](../00-backend_architecture/00-backend_architecture/08-sprint-roadmap.md) §6): **"the first real Prediction exists."**

---

## 2. Build Order (Layer by Layer, Analysis Module Only)

```text
1. Domain
   ├── Prediction (domain object / invariants from 02-domain.md §6)
   └── MLResponseValidator — confirms a response has all 5 required fields, valid ranges,
        a verdict inside the enum, BEFORE any write is attempted (see 04-ml-client-contract.md §4)

2. Infrastructure
   ├── PredictionModel (Eloquent)
   ├── PredictionRepository
   │     ├── create()
   │     └── findByObservationId()
   ├── PredictionLookupContract (interface) + implementation
   │     — the surface Alert (Stage 5) will later consume
   └── MLClient
         — wraps the HTTP call to the ML Engine (see 04-ml-client-contract.md §3),
           translates transport failures into a typed exception the Application
           layer can catch uniformly

3. Application
   ├── AnalyzeObservationAction
   │     — called by the Queue Worker; calls ObservationLookupContract to fetch the
   │       Observation, calls MLClient, validates the response via MLResponseValidator,
   │       calls PredictionRepository::create() on success, calls Observation's
   │       markCompleted/markFailed accordingly
   └── ClaimPendingObservationsAction
         — called by the Poller; calls a new Observation-exposed method (see §3 below)
           to select and claim the next batch, then dispatches one Job per claimed
           Observation

4. Infrastructure (Queue)
   ├── AnalyzeObservationJob (implements Laravel's ShouldQueue)
   │     — thin: fetches its one observationId, delegates entirely to
   │       AnalyzeObservationAction; tries=3, backoff per 04-ml-client-contract.md §5
   └── PollPendingObservationsCommand (Artisan Command, scheduled)
         — thin: delegates entirely to ClaimPendingObservationsAction

5. Presentation
   └── PredictionSummaryResource
         — the 5-field embedding shown in 07-api-contract.md §1 (not the full
           prediction_json — see that file's note on Dashboard's future role)
```

---

## 3. A New Method Observation Must Add, Discovered During This Sprint's Design

[`docs/backend/observation/08-implementation-roadmap.md`](../04-observation/08-implementation-roadmap.md) anticipated `markProcessing`/`markCompleted`/`markFailed` and `ObservationLookupContract` (single-ID lookup), but not a **batch claim** method — because Stage 3 had no consumer to design it against yet. This Sprint requires adding exactly one new method to Observation's already-existing `ObservationRepository`:

```text
Observation\Infrastructure\ObservationRepository
    └── claimNextPendingBatch(limit: int): Observation[]
          — SELECT ... WHERE analysis_status = 'PENDING' ORDER BY received_at ASC
            LIMIT :limit, then atomically transitions each claimed row to PROCESSING
            in the same call (avoiding the race window described in
            03-processing-pipeline.md §4 between a separate SELECT and a separate
            markProcessing per row)
```

**This is a small, additive change to the Observation module — not a rewrite.** It follows the exact same pattern (a narrow, explicit, Observation-owned method that Analysis calls into) as everything else already documented; it is called out explicitly here so it isn't missed as "someone else's problem" during implementation. Whoever implements Sprint 4 is expected to add this one method to the Observation module's existing Infrastructure layer as part of this Sprint's own work.

---

## 4. What Sprint 4 Explicitly Does NOT Build

```text
✘ Any Alert-related code, table interaction, or policy evaluation logic
✘ Any change to Observation's Domain, Application, or Presentation layers (only the one
  Infrastructure method above, plus the one Controller composition line in 07-api-contract.md §2)
✘ Re-analysis / re-processing of an already-COMPLETED or already-FAILED Observation
✘ Any Dashboard aggregation, statistics, or widget — Stage 6's job
```

---

## 5. Tests Required (Following the Engineering Workflow's Five Categories)

```text
1. Happy Path
   ✔ A PENDING Observation is claimed, sent to ML, and a matching Prediction is stored
   ✔ The Observation's analysis_status becomes COMPLETED, with processed_at set
   ✔ GET /observations/{id} returns a fully populated prediction object after processing

2. Edge Case
   ✔ Two Poller runs in quick succession never both claim the same Observation
     (concurrency test against claimNextPendingBatch)
   ✔ An ML response missing a required field (e.g., no verdict) → Observation marked
     FAILED, no Prediction row created
   ✔ An ML response with risk_score outside 0–100 → same as above
   ✔ ML Engine times out / connection refused → Observation marked FAILED after
     retries are exhausted, not left stuck in PROCESSING forever

3. Business Rule
   ✔ Every field on the stored Prediction matches the ML response's value exactly —
     byte-for-byte for prediction_json, exact value for the 5 promoted columns
   ✔ A FAILED Observation is never picked up again by a later Poller run
   ✔ analysis_options is sent as {} (or per whatever the current documented default is) —
     never populated with invented fields

4. Authorization
   ✔ (Largely inherited from Observation's own tests — see docs/backend/observation/
     08-implementation-roadmap.md §4 — but add:) a Member can view a COMPLETED
     Observation's prediction data exactly as an Owner can — no Role distinction here

5. Data Isolation
   ✔ (Inherited entirely from Observation's own scoping — GET /observations/{id}'s
     Organization check runs before Analysis's composition step is ever reached, so
     there is no new cross-tenant surface introduced by this module)
```

---

## 6. Sprint 4 Exit Checklist

```text
☐ predictions table already exists (Stage 1/Database — no new migration needed)
☐ PredictionLookupContract implemented, ready for Alert to consume in Stage 5
☐ claimNextPendingBatch added to Observation's ObservationRepository (see §3)
☐ MLClient implemented against a configurable ML_SERVICE_URL, with the retry/backoff
  policy from 04-ml-client-contract.md §5
☐ Poller (scheduled Command) and Queue Worker (Job) both implemented and tested
☐ GET /observations/{id}'s Controller updated with the one composition line from
  07-api-contract.md §2 — confirm no other Observation-module file needed to change
☐ A real, end-to-end demo works: submit an Observation, watch it move
  PENDING → PROCESSING → COMPLETED, and see the Prediction in the API response
☐ docs/backend/analysis/ (this folder) marked Frozen once code matches it exactly
```
