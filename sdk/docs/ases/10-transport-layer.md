# 10 — Transport Layer

> Source concept: Session 7. Any SDK can build a JSON payload. Very few know how to send it correctly, without ever becoming the reason a customer's Agent breaks. This document is what separates "Production" from "Demo."

---

## 1. Is the SDK's Job to Send an HTTP Request?

**No — and this is a deliberately surprising starting point.**

> **The SDK's job is to ensure the Observation reaches SentinelX safely, without affecting the Agent's execution.**

Notice the difference from *"Send Request."* Consider the failure scenario this framing is built to survive:

```text
Agent finishes a Task.
    ↓
Builder constructs the Observation.
    ↓
Transport sends the Request.
    ↓
Network Timeout.
```

Throwing an exception here would break the customer's Agent because of a SentinelX network hiccup — completely unacceptable. This produces the single most important rule in this entire document:

> **ASES must never be the reason an Agent fails.**

---

## 2. Passive by Design

Transport must be **Passive** — even if the SentinelX backend goes down entirely, the Agent must keep running unaffected. This is a Production-grade requirement, not a nice-to-have.

---

## 3. Does the Agent Wait for the Request to Finish?

**No.** If the API takes 500ms to respond, every single Task the Agent runs would be delayed by that amount — unacceptable. Transport must therefore be **asynchronous**:

```text
Observation Ready
    ↓
Queue
    ↓
Agent continues its own work
    ↓
Transport sends in the background
```

The Agent's own execution finishes *before* the HTTP call even happens — a deliberately elegant outcome.

---

## 4. What Kind of Queue?

Not RabbitMQ. Not Kafka.

> **An in-memory queue.**

The SDK lives inside a single process — it is not a distributed system, and reaching for distributed-systems infrastructure here would be over-engineering solving a problem that doesn't exist at this scale.

---

## 5. The Transport Lifecycle

```text
Observation
    ↓
Enqueue
    ↓
Worker
    ↓
HTTP Request
    ↓
Success → Delete
Failure → Retry
```

### Retry Policy

The retry count is deliberately **not** exposed as a customer-configurable setting — it's an internal implementation detail. The adopted policy — **3 retries, then drop, logged as a warning, never raised as an exception** — applies to *transient* failures specifically. Not every failure the API Client can observe is transient, so three classes are distinguished (RC-8, IDENTITY-002):

```text
Transient (connection error, timeout, 5xx)   →  the policy above, unchanged.
Authentication (401 / 403)                     →  NOT retried. A bad credential
                                                     cannot succeed on attempt 2
                                                     or 3. Fails fast, logged
                                                     immediately and distinctly
                                                     ("authentication failed —
                                                     check your ASES_API_KEY
                                                     configuration"), consuming
                                                     none of the retry budget.
Rate limit (429)                                 →  retried, but honoring the
                                                     Backend's own Retry-After
                                                     header when present, rather
                                                     than the fixed backoff below
                                                     — falls back to that backoff
                                                     only if no Retry-After header
                                                     is supplied.
```

This was previously undocumented — the original rationale below was written entirely around the transient case, and applying it uniformly to an authentication rejection would mean retrying an invalid credential three times for no possible benefit before silently dropping the Observation with only a generic warning.

### The Four Timeout-Shaped Concepts, Named Together (RC-9, RELIABILITY-001/002)

This document, `09-observation-lifecycle.md`, and `environment-configuration.md` each address one piece of "how long does X wait" — previously scattered enough that reconstructing which timeout governs what required cross-referencing all three. Named here together, once:

| Concept | Value | Governs |
|---|---|---|
| Per-request send timeout | **10 seconds** | How long a single HTTP attempt to the Backend is allowed to run before it counts as a failure requiring a retry. Mirrors the Backend's own outbound budget for its Backend→ML call (`MLClient.php`: `timeout(10)`), for consistency across this project's various outbound HTTP calls. |
| Shutdown Flush timeout | **5 seconds** | How long the shutdown Flush (below) is allowed to run in total, across every still-queued Observation, before the process exits regardless. |
| Observation-lifecycle timeout | 30 seconds (`09-observation-lifecycle.md §3`) | How long the Collector waits for a new Event before force-closing an in-flight Observation — a Lifecycle concept, unrelated to network I/O. |
| Retry count | 3 (not a timeout) | How many times a transient/rate-limited failure is retried before the Observation is dropped. |

All four are adjustable engineering defaults, not frozen business rules — the same discipline this codebase already applies to values like the Alert module's `SeverityMapper` thresholds — and, per `environment-configuration.md §7`, none of them is customer-configurable in V1.

### Send Rate / Backpressure — a Stated Assumption, Not a Silent One (RC-9, RELIABILITY-003)

The Worker drains the Queue and hands each Observation to the API Client individually — there is no batching, throttling, or client-side rate limiting of the SDK's own send rate anywhere in this design. This is a real, deliberate assumption, stated explicitly here rather than left unaddressed: **the SDK relies on the Backend's own rate limiter (`observation-ingestion`, 300 requests/minute per Agent — `backend/routes/api.php`, `AppServiceProvider.php`) as the governing ceiling**, not a client-side one. A burst of concurrent Observations completing near-simultaneously (a normal, expected scenario — `09-observation-lifecycle.md §4`) can therefore hit that ceiling; when it does, the Backend responds `429`, and the Retry Policy above already handles it correctly (honoring `Retry-After`) — this is the proportionate response to a real burst, not a reason to build client-side batching or throttling with no demonstrated need for it.

### If the Network Disconnects Entirely
The Observation simply remains in the Queue. Once connectivity returns, the Worker resumes automatically — expected, normal behavior, nothing special required.

### If the SDK Is Shutting Down
Before shutdown completes, Transport performs a **Flush** — a best-effort attempt to send whatever remains queued, bounded by a short timeout (**5 seconds** — see the timeout table above). If it can't finish in time, it simply stops; a lost final Observation on shutdown is an accepted, bounded tradeoff, not a defect.

### Should the Queue Persist to Disk?

**Not in V1.** This follows directly from the Observation Lifecycle decision (see [`09-observation-lifecycle.md`](./09-observation-lifecycle.md)) that an Observation's life is measured in seconds. If the process crashes, the Agent itself has crashed — losing the last in-flight Observation in that scenario is acceptable for V1.

### What a Permanently-Dropped Observation's Log Entry Actually Contains (RC-9, RELIABILITY-005)

Two log lines together, not one — deliberately not duplicated into a single message, so they can never drift out of sync with each other:

```text
1. The API Client's own warning (transport/client.py), logged at the point
   delivery finally failed — states the specific reason: which of the
   three failure classes above applied (retries exhausted / authentication
   failure / non-retryable rejection), including the HTTP status where
   relevant. Never includes the api_key (see 08-internal-architecture.md
   §7's explicit "must never log" rule).
2. The Worker's own follow-up warning (transport/worker.py), logged
   immediately after — states this Observation's internal correlation_id
   (the Collector's own grouping key; never the wire-format JSON, which
   carries no such field at all) and its event_count, and points back to
   the preceding line for the reason.
```

Both lines carry a timestamp via the standard log format (`shared/logger.py`: `[%(asctime)s] %(levelname)-8s ases.%(name)s: %(message)s`) — not a separate, message-embedded field.

---

## 6. Who Knows What, Inside Transport

```text
Does Transport know the API Key?          Yes — via Configuration, never from
                                              the Builder, Adapter, or Collector.
Who performs Serialization?                 Transport — not the Builder.
Who knows the Endpoint?                       Transport only.
Who knows Authentication?                       Transport only.
Who knows the Retry policy?                       Transport only.
```

**What "knowing Authentication" means mechanically (RC-8, IDENTITY-001):** the API Client attaches the resolved `api_key` as a single custom header, `X-API-Key: <api_key>` — never `Authorization: Bearer`, which is a distinct scheme reserved for the Backend's separate, JWT-based Human guard. Confirmed directly against the real Backend's Agent guard (`backend/app/Modules/Authentication/AuthServiceProvider.php:34-39`). This was previously unspecified anywhere in this document set — a genuine gap, not a stylistic omission, since no correct SDK implementation could be built against this document alone without it.

### Why Serialization Belongs to Transport, Not the Builder

The Builder constructs an in-memory object; **Transport** is what knows it's specifically talking HTTP, and therefore Transport is what turns that object into JSON. If a future version sends data over gRPC instead of HTTP, the Builder changes **zero lines** — only Transport's Serializer would need to change. This clean separation is exactly what makes such a future change cheap instead of invasive.

---

## 7. Transport's Own Internal Shape

Transport itself is not a monolith — it decomposes into four focused pieces:

```text
Queue        — holds Observations awaiting delivery.
Worker         — a background loop that pulls from the Queue.
Serializer       — converts an Observation object into JSON.
API Client         — the only thing that actually knows how to speak HTTP:
                       authentication, requests, retry policy, endpoint management.
```

The Worker itself does not send anything — it delegates to the API Client. This decomposition means a future switch to a different transport mechanism (e.g., a message broker) only touches the API Client and/or Serializer, never the Queue or Worker.

---

## 8. The Full Transport Picture

```text
Builder
    ↓
Observation Object
    ↓
Transport Queue
    ↓
Background Worker
    ↓
Serializer
    ↓
HTTP Client
    ↓
SentinelX API
```

Note: the Builder never sees JSON, HTTP, or the API — a deliberate and valuable ignorance.

---

## 9. Where Does SDK Responsibility End?

> **Does the SDK ever learn whether ML returned a Prediction? No.**

The SDK's responsibility ends the moment SentinelX responds `202 Accepted`. Everything that happens afterward — analysis, prediction, alerting — happens entirely inside the server, and is none of the client's concern. This is exactly what keeps the Client and Backend cleanly decoupled — full detail on this boundary is in [`ADR-005-sdk-responsibility-boundary.md`](./adr/ADR-005-sdk-responsibility-boundary.md).

---

## 10. The Complete Picture of the Whole Layer

```text
Framework
    │
    ▼
Adapter
    │
    ▼
Event Pipeline
    │
    ▼
Observation Collector
    │
    ▼
Observation Builder
    │
    ▼
Observation Validator
    │
    ▼
Transport Queue
    │
    ▼
Background Worker
    │
    ▼
Serializer
    │
    ▼
API Client
    │
    ▼
SentinelX REST API
```

This is, at this stage of the design, the first point where the full architecture becomes something that can genuinely be visualized directly as code.

---

## 11. Summary

```text
Transport Layer

Goal
Deliver observations to SentinelX without affecting Agent execution.

────────────────────────

Principles
- Never block the Agent.
- Never crash the Agent.
- Retry internally.
- Fail gracefully.

────────────────────────

Pipeline
Observation → Queue → Background Worker → Serializer → API Client
→ SentinelX REST API

────────────────────────

Queue
- In-memory
- FIFO
- Lightweight
- No external dependencies

────────────────────────

Worker
- Background process
- Pulls observations from the queue
- Invokes the API Client

────────────────────────

Serializer
- Converts Observation objects into JSON
- Independent from the Builder

────────────────────────

API Client
Responsibilities:
- Authentication
- HTTP Requests
- Retry Policy
- Endpoint Management

────────────────────────

Retry Policy
- 3 retries
- Log warning
- Drop observation

────────────────────────

Shutdown
Flush queue with a short timeout.

────────────────────────

SDK Responsibility Ends
After SentinelX accepts the observation (HTTP 202 Accepted).
```

---

## 12. Error & Log Message Reference (RC-10, DX-005)

This session's own "Errors Teach" principle names a specific standard: a good message ("API Key is missing. Please configure Sentinel before sending observations.") versus a bad one ("Invalid configuration"). No document previously quoted a single actual ASES message to check against that standard. The literal messages this codebase raises or logs, verified directly against source, in the two places they occur:

**Configuration error — raised directly into customer code, at setup time** (`config/settings.py` — the one category of error the SDK is allowed to raise, since it happens before any Agent Task is running):

```text
ASES api_key is required. Pass it explicitly to ASES(api_key=...) or
configure(api_key=...), or set the ASES_API_KEY environment variable.
```

```text
configure() may only be called once per process. It has already been called.
```

**Authentication failure — logged, never raised, per the Retry Policy above** (`transport/client.py`, RC-8's IDENTITY-002 classification):

```text
Observation dropped: authentication failed (HTTP 401) — check your
ASES_API_KEY configuration.
```

**Permanently-dropped Observation — the two-line shape specified in section 5 above** (`transport/worker.py`, RC-9's RELIABILITY-005):

```text
[API Client's own preceding line, e.g.:]
SentinelX rejected the Observation: HTTP 422 (attempt 1/1).

[Worker's follow-up line:]
Observation dropped (correlation_id={"execution_id": "abc-123"}, event_count=2)
— see the preceding warning from transport.client for the failure reason.
```

Every message above names the specific thing that went wrong and, where an action exists, states it directly (which environment variable to set, which configuration to check) — matching the "teaches" standard rather than the "punishes" one. None of them include the `api_key` value itself (see `08-internal-architecture.md §7`'s explicit "must never log" rule). Any new message added to this codebase should be checkable against this same reference, not invented ad hoc.
