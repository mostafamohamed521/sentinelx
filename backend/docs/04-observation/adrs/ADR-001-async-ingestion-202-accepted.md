# ADR-001: Observation Submission Returns 202 Accepted, Not 201 Created

| | |
|---|---|
| **Status** | ✅ Accepted |
| **Scope** | Observation Module, Sprint 3 |
| **Affects** | `POST /observations` response contract, SDK integration expectations |

---

## Context

`POST /observations` is where a new `observations` row genuinely comes into existence — by REST convention, that would normally warrant `201 Created`, exactly as `POST /agents` does in the Agent module. But [`docs.zip/09-api-reference/04-OBSERVATIONS_API.md`](../../docs/docs/09-api-reference/04-OBSERVATIONS_API.md) already specifies `202 Accepted`, and the frozen processing pipeline diagram explicitly continues past persistence into `Queue → ML Analysis → Prediction Stored`.

---

## Decision

`POST /observations` returns `202 Accepted`, both in Stage 3 (before any of the async pipeline actually exists) and after Stage 4 ships. The response body never grows to include analysis results — see [`07-api-contract.md`](../07-api-contract.md) §1.

---

## Rationale

### Why `202` instead of `201`, when in Stage 3 the write genuinely does complete synchronously?
Because the *contract*, not the current implementation state, is what SDK integrators build against. `201 Created` promises the caller: *"the resource you asked for now exists, in the form you'll see if you fetch it again."* For an Observation, that promise is misleading — the record that matters to the platform's actual purpose (a security-analyzed Observation) is not "complete" the moment the row is inserted; it becomes complete only once analysis finishes, at some later, indeterminate time, potentially by a completely different service (the ML Engine) that this module has zero visibility into. `202 Accepted` accurately communicates: *"your request was valid and has been queued for further processing you cannot observe synchronously."*

### Why decide this now, in Stage 3, before Queue/Worker/ML exist?
Because the HTTP status code is part of the public contract every SDK is written against. Shipping `201` now and changing to `202` once Stage 4 lands would be a breaking change for every existing integrator — forcing them to change how they interpret a successful response mid-flight. Committing to the correct long-term semantics from the very first version avoids ever needing that migration.

### Why not include a `Location` header pointing at `GET /observations/{id}`, as REST convention often pairs with `202`?
Considered, and left as an implementation detail rather than a hard requirement — the response body's `id` field already gives the caller everything needed to construct that URL themselves (`GET /observations/{id}`), and the SDK, per [`ADR-010-SDK-Responsibilities`](../../docs/docs/07-adrs/ADR-010-SDK-Responsibilities.md), has no legitimate reason to poll that endpoint at all — polling for analysis results is a Dashboard/Human concern, not an SDK one.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| `201 Created`, matching the Agent module's convention | Misrepresents the Observation's actual lifecycle state; would require a breaking contract change once Stage 4 ships |
| `200 OK` with the full (eventually analyzed) Observation, making the client poll or wait | Directly violates [`ADR-006-Backend-as-Orchestrator`](../../docs/docs/07-adrs/ADR-006-Backend-as-Orchestrator.md)'s async design and would force the SDK to block on ML latency, which the platform's own architecture explicitly avoids |
| `200 OK` with `analysis_status` and nothing else, no distinct status code semantics | Loses the standard REST signal that distinguishes "resource created" from "request accepted for further processing" — makes client-side handling (e.g., generic HTTP client libraries treating `2xx` differently) less predictable |

---

## Consequences

- ✅ The response contract is stable across Stage 3 and Stage 4 — no breaking change when Analysis ships.
- ✅ SDK implementers correctly build against "fire and forget" semantics from day one, rather than needing a migration guide later.
- ✅ Matches the platform's own frozen architectural stance (Backend as Orchestrator, asynchronous ML integration).
- ⚠️ Some HTTP client tooling treats `202` less conventionally than `201`/`200` — this is a minor, well-understood integration detail the SDK documentation must call out explicitly.
