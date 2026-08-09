# 04 — Validation Scope

> The single easiest place for this module's boundary to quietly expand. This file exists to draw the line precisely, using [`ASES_JSON_SCHEMA.md`](../docs/docs/03-specifications/03-ASES_JSON_SCHEMA.md)'s own "Validation Rules" section as the ceiling — nothing above it belongs here.

---

## 1. What "Valid" Means, Exactly (the Ceiling)

Per [`ASES_JSON_SCHEMA.md`](../docs/docs/03-specifications/03-ASES_JSON_SCHEMA.md) §"Validation Rules," an Observation must:

```text
1. Be valid JSON
2. Follow the official schema (Context / Events / Metadata, correct required fields)
3. Include all required fields
4. Preserve chronological event ordering
5. Include at least one Event
```

That list is the **entire** scope of what this module is allowed to check. Nothing more.

---

## 2. What Validation Explicitly Does NOT Mean Here

```text
✘ Is this event_type in the Event Dictionary?                         → checked (structural — it's an enum-shaped field)
✘ Does this api_call event's payload represent a real attack?          → NEVER — ML Engine's job
✘ Is this file_access event suspicious given the Agent's history?       → NEVER — ML Engine's job
✘ Does the sequence of events look anomalous?                            → NEVER — ML Engine's job
✘ Is the framework_version compatible with the SDK version?               → NEVER checked at all — no business rule
                                                                              requires this in V1
```

The distinction: this module validates **shape** (does the JSON look like a well-formed ASES document?). It never validates **meaning** (does what happened look dangerous?). Per [`ASES_SPECIFICATION.md`](../docs/docs/03-specifications/01-ASES_SPECIFICATION.md) §"Ownership": *"ASES defines only the structure of an Observation. It does not define: Security severity, Threat classification, Attack taxonomy, Risk scoring, ML predictions. Those responsibilities belong exclusively to the Machine Learning Engine."*

---

## 3. The Five Checks, One by One

### Check 1 — Valid JSON
The request body must parse as JSON at all. Malformed JSON (a transport-level problem) fails before this module's own validator ever runs — the framework's own JSON body parser handles this, returning `400 BAD_REQUEST` (see [`07-api-contract.md`](./07-api-contract.md)).

### Check 2 & 3 — Schema Conformance / Required Fields
```text
context.framework              required, string
context.execution_start_time    required, ISO 8601 timestamp
context.execution_finish_time    required, ISO 8601 timestamp, >= execution_start_time
events                             required, array
events[].header.event_type          required, must be one of the 10 canonical Event
                                       Dictionary types (see 02-domain.md §2)
events[].header.timestamp             required, ISO 8601 timestamp
events[].payload                        required, object (any shape — payload content
                                           is never validated beyond "is an object")
metadata.spec_version                     required, string (e.g. "1.0")
metadata.sdk_version                       required, string
```

**`events[].payload`'s internal shape is intentionally never validated beyond "is a JSON object."** Per the Event Dictionary's own scope, payload content is framework- and event-type-specific and evolves independently — this module has no business asserting what a `tool_execution` payload must contain versus a `database_operation` payload.

### Check 4 — Chronological Ordering
```text
events[i].header.timestamp <= events[i+1].header.timestamp   for every i
```
A violation here means the SDK sent events out of order — a structural defect, not a security judgment, and therefore squarely this module's concern to reject.

### Check 5 — At Least One Event
```text
events.length >= 1
```
An Observation with zero Events carries no information for the ML Engine to ever analyze — rejected here rather than accepted and left permanently `PENDING`.

### Check 6 — Maximum Event Count (PERF-004)
```text
events.length <= 1000
```
No upper bound existed anywhere in the Backend or ML Service prior to the integration audit's PERF-004 finding — an unbounded payload is an unbounded worst-case processing cost per Observation. `1000` is an adjustable engineering default (`ObservationValidator::MAX_EVENTS`), not a frozen business rule; large enough for legitimate batched SDK submissions, rejected here at the same structural layer as Check 5 rather than left to fail later, unpredictably, deeper in the pipeline.

---

## 4. Where This Logic Lives (Layer Placement)

Per [`06-implementation-layers.md`](../00-backend_architecture/00-backend_architecture/06-implementation-layers.md):

```text
API layer         → confirms the request body is present and is JSON at all (400 if not)
Domain layer       → ObservationValidator — implements Checks 2–5 above, framework-agnostic,
                       no Laravel/Eloquent/HTTP concepts, pure PHP against the parsed payload
Application layer    → ReceiveObservationAction calls ObservationValidator before ever
                          touching the Repository; on failure, returns immediately with no write
```

**Never inside the Controller, never inside the Repository.** A `FormRequest` alone is not expressive enough for rule 4 (cross-array-element ordering) — this belongs in the Domain layer's `ObservationValidator`, not a simple Laravel validation rule set, even though Checks 1–3 and 5 could technically be expressed as FormRequest rules. Keeping all five checks together in one Domain validator (rather than splitting them between FormRequest and Domain) keeps the full definition of "valid ASES" in exactly one place.

---

## 5. Failure Response

Any of the five checks failing produces the same outcome — **no partial acceptance, no partial persistence:**

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted observation is invalid.",
    "details": {
      "field": "events",
      "reason": "at least one event is required"
    }
  }
}
```

`details` may vary per failing check, but the top-level `code` is always `VALIDATION_ERROR` and the status is always `422` — see [`07-api-contract.md`](./07-api-contract.md) §1 for the full response contract.
