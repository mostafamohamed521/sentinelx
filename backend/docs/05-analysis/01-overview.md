# 01 — Analysis Module Overview

> Extends [`backend-architecture/03-system-modules.md`](../00-backend_architecture/00-backend_architecture/03-system-modules.md) and [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §6. Nothing here contradicts them.

---

## 1. What Is a Prediction, In One Sentence?

> **A Prediction is the ML Engine's interpretation of one Observation, captured permanently as its own historical record — never mixed with, and never replacing, the raw execution facts it was derived from.**

Per [`ADR-015-Observation-Prediction-Separation`](../docs/docs/07-adrs/ADR-015-Observation-Prediction-Separation.md): *"An Observation represents factual execution data... A Prediction represents an interpretation of that data... Predictions may evolve over time as ML models improve, while the original Observation remains unchanged."*

---

## 2. What the Analysis Module Is Responsible For

```text
Find Observations waiting for analysis (analysis_status = PENDING)
Send them, unmodified, to the ML Engine
Receive the ML Engine's response
Store the response as a Prediction, exactly as returned
Advance the originating Observation's analysis_status accordingly
```

## 3. What the Analysis Module Is Explicitly NOT Responsible For

```text
✘ Deciding whether an event is malicious           → ML Engine only
✘ Computing a risk score, confidence, or verdict     → ML Engine only
✘ Deciding whether an Alert should be created         → Alert module (Stage 5), reading Analysis's
                                                            output — never triggered by Analysis itself
✘ Storing or validating raw_ases_json                   → Observation module (already done, Stage 3)
✘ Inspecting event semantics itself                      → never — this module hands the payload to
                                                              ML exactly as Observation stored it
```

Per [`ADR-006-Backend-as-Orchestrator`](../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md): the Backend's role, everywhere including inside this module, is orchestration — authentication, validation, persistence, ML communication, and (later) alert *evaluation* — never threat classification itself.

---

## 4. The One Rule This Module Never Breaks

> **The Analysis module never computes, infers, or overrides a security judgment. Every field on a Prediction is copied from the ML Engine's response, verbatim.**

If a future engineer's instinct is *"the ML Engine seems unsure here (confidence 0.51) — let me add a rule that bumps low-confidence SUSPICIOUS verdicts down to SAFE"* — that instinct is exactly the mistake [`ADR-003-ML-Responsibility`](../docs/docs/07-adrs/ADR-003-ML-Responsibility.md) exists to prevent. Any such policy, if ever wanted, is a Backend-side decision about *whether to alert* (squarely [`ADR-011-Alert-Generation-Policy`](../docs/docs/07-adrs/ADR-011-Alert-Generation-Policy.md)'s territory, i.e. Alert module, Stage 5) — never a reason to alter what gets stored as the Prediction itself.

---

## 5. Why Analysis Is Its Own Module and Not Folded Into Observation

Already explained from Observation's side in [`docs/backend/observation/01-overview.md`](../04-observation/01-overview.md) §5, restated from Analysis's side: folding ML communication into Observation would couple a fast, simple, synchronous write path (accept and store) to a slow, potentially-failing, asynchronous external dependency (the ML Engine). Per [`04-module-responsibilities.md`](../00-backend_architecture/00-backend_architecture/04-module-responsibilities.md) §6's own framing: *"Begins exactly where Observation ends... Kept separate from Observation because analysis logic may evolve — but ingestion must remain stable."*

---

## 6. Route Ownership vs. Module Ownership (Same Pattern, Third Occurrence)

Unlike Agent (Stage 2) and Observation (Stage 3), **the Analysis module owns no standalone public route of its own.** There is no `GET /predictions` or `POST /predictions` anywhere in the frozen [`09-api-reference/`](../docs/docs/09-api-reference/) contract. Its only public-facing footprint is completing a field on a route owned by Observation:

| Route | Implemented By | Analysis's Role |
|-------|------------------|--------------------|
| `GET /observations/{id}` | **Observation module** (owns the route) | **Analysis module** populates the `prediction` field, per [`docs/backend/observation/adr/ADR-003-prediction-composition-deferred.md`](../04-observation/adr/ADR-003-prediction-composition-deferred.md) — this is precisely the deferred work that ADR promised |

Everything else this module does is entirely internal — background processing, no HTTP surface of its own.

---

## 7. Session Summary

```text
Analysis Module — Overview

Owns
✔ Prediction entity
✔ prediction_json (Evidence lives inside — see 02-domain.md §3)
✔ Driving analysis_status forward (via Observation's own exposed write methods)

Does Not Own
✘ Observation
✘ The security judgment itself (that's the ML Engine's)
✘ Alerts

Golden Rule
✔ Every Prediction field is copied from the ML Engine's response, verbatim.
✔ This module owns no public route of its own — it completes one field on
  Observation's route.
```
