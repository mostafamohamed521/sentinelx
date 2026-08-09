# 03 — Ingestion Pipeline

> Implements the pipeline already frozen in [`OBSERVATIONS_API.md`](../docs/docs/09-api-reference/04-OBSERVATIONS_API.md) and [`DATA_LIFECYCLE.md`](../docs/docs/08-database/03-DATA_LIFECYCLE.md), scoped to exactly the part this module owns.

---

## 1. The Frozen Pipeline, Annotated by Ownership

```text
Receive              → Observation module (API layer)
    ↓
Authenticate          → Authentication module (API Key submodule) — NOT this module
    ↓
Validate               → Observation module (structural validation only — see 04-validation.md)
    ↓
Persist                  → Observation module — pipeline ends here for Stage 3
    ↓
Queue                     → Analysis module (Stage 4) — out of scope here
    ↓
ML Analysis                → Analysis module (Stage 4) — out of scope here
    ↓
Prediction Stored            → Analysis module (Stage 4) — out of scope here
```

This module implements exactly three of the seven steps in the frozen pipeline diagram. The other four are real, and documented here for context, but belong to Analysis.

---

## 2. Step 1 — Authenticate (Not This Module, But a Hard Prerequisite)

Before `ReceiveObservationAction` is ever invoked, the request has already passed through the API Key middleware, exactly as specified in [`02-auth/05-api-keys.md`](../02-auth/02-auth/05-api-keys.md) §6 and [`contracts/api-key-format.md`](../02-auth/02-auth/contracts/api-key-format.md) §3:

```text
Extract API Key (header)
    ↓
Hash it
    ↓
Look up ACTIVE key_hash
    ↓
Resolve Agent
    ↓
Resolve Organization
    ↓
AuthenticatedIdentity { id: agentId, type: "AGENT", organization_id }
```

**By the time the Observation module's Controller runs, it never sees a raw API Key, never queries `api_keys`, and never independently verifies the Agent's existence via its own query** — it trusts the `AuthenticatedIdentity` object handed to it by the Authentication middleware. This is the same principle already established for the Agent module in Stage 2.

**Archived-Agent case:** if the resolved Agent's `status = ARCHIVED`, the request never reaches this module at all — Authentication already returns `401` per the frozen error contract ([`02-auth/contracts/auth-errors.md`](../02-auth/02-auth/contracts/auth-errors.md) §2, "Archived Agent" row).

---

## 3. Step 2 — Receive

```text
POST /api/v1/observations
Body: raw ASES JSON (Context + Events + Metadata)
```

The Controller's only job: pass the raw request body, untouched, plus the `AuthenticatedIdentity`, to `ReceiveObservationAction`. No parsing, no partial deserialization, no field extraction happens in the API layer.

---

## 4. Step 3 — Validate

Delegated entirely to a Domain-layer validator — see [`04-validation.md`](./04-validation.md) for the exact scope. Produces one of two outcomes:

```text
Valid   → proceed to Persist
Invalid → 422 VALIDATION_ERROR, nothing is written to the database
```

---

## 5. Step 4 — Persist

```text
ReceiveObservationAction
    │
    ├── organization_id  ← from AuthenticatedIdentity
    ├── agent_id          ← from AuthenticatedIdentity
    ├── raw_ases_json      ← the exact validated payload, unmodified
    ├── analysis_status     ← PENDING (always)
    ├── received_at          ← now() at the moment the request was accepted
    │
    └── INSERT INTO observations
```

**Side effect, in the same transaction:** `AgentRepository::touchLastSeen(agentId, receivedAt)` — see [`05-cross-module-boundaries.md`](./05-cross-module-boundaries.md) §1 for why this is the one legitimate write this module makes outside its own table.

---

## 6. Why the Response Is `202 Accepted`, Not `201 Created`

Per [`OBSERVATIONS_API.md`](../docs/docs/09-api-reference/04-OBSERVATIONS_API.md): *"The Observation has been accepted for asynchronous processing."* This is a deliberate, meaningful distinction from the Agent module's `201 Created` responses:

```text
201 Created  → "the resource now exists, exactly as returned, complete."
202 Accepted → "the request is accepted; the full lifecycle (analysis) is not complete yet."
```

Even in Stage 3, before Analysis exists at all, the contract already commits to `202` — because the Observation record genuinely isn't "done" from the platform's perspective the moment it's stored; it is only done once analyzed. Returning `201` now and trying to change it to `202` later, once Analysis ships, would be a breaking API change for every SDK integrator. See [`adr/ADR-001-async-ingestion-202-accepted.md`](./adr/ADR-001-async-ingestion-202-accepted.md).

---

## 7. What the SDK Receives Back

```json
{
  "data": {
    "id": "0198c3a1-...",
    "received_at": "2026-07-29T10:00:00Z",
    "analysis_status": "PENDING"
  }
}
```

Deliberately minimal. The SDK's only remaining responsibility (per [`ADR-010-SDK-Responsibilities`](../docs/docs/07-adrs/ADR-010-SDK-Responsibilities.md)) is to know the submission succeeded — it has no need for, and must never receive, any analysis result synchronously, since none exists yet at this point in the pipeline.

---

## 8. Full Sequence

See the rendered version at [`diagrams/observation-ingestion-sequence.svg`](./diagrams/observation-ingestion-sequence.svg).

```text
SDK                     Backend (Auth)              Observation Module            DB
 │                            │                              │                     │
 │── POST /observations ────►│                              │                     │
 │   (API Key header)         │── verify key, resolve       │                     │
 │                            │   Agent + Organization       │                     │
 │                            │── AuthenticatedIdentity ────►│                     │
 │                            │                              │── validate ASES     │
 │                            │                              │   shape             │
 │                            │                              │── INSERT ──────────►│
 │                            │                              │── touchLastSeen ───►│
 │◄── 202 Accepted ───────────────────────────────────────────│                     │
```

---

## 9. Summary

```text
Ingestion Pipeline (this module's scope)

Receive    → Controller passes raw payload + Identity to an Action, untouched
Authenticate → not this module — a hard prerequisite enforced upstream
Validate     → structural shape only, see 04-validation.md
Persist       → one INSERT, plus one legitimate cross-module write (last_seen_at)
Response       → 202 Accepted, minimal body, no analysis result
```
