# ADR-004: Transport Is Asynchronous, Passive, and Never Blocks or Crashes the Host Agent

| | |
|---|---|
| **Status** | ✅ Accepted (Frozen) |
| **Source** | Session 7 — Transport Layer |
| **Affects** | The entire Transport implementation: Queue, Worker, Serializer, API Client, retry behavior, and shutdown handling |

---

## Context

Once an Observation is built and validated, it needs to reach the SentinelX backend over the network. Because this code runs embedded inside a customer's own Agent process (see [`01-overview.md`](../01-overview.md)), a decision was needed about how network failures, latency, and delivery guarantees should be handled without ever compromising the host Agent's own execution.

---

## Decision

Transport is built around one governing rule:

> **ASES must never be the reason an Agent fails.**

Concretely:
- Delivery is **asynchronous** — the Agent's own execution completes before the HTTP request happens, via an **in-memory FIFO Queue** and a background **Worker**.
- Delivery is **Passive** — even a complete SentinelX outage must not affect the Agent.
- Failures **retry silently**: 3 attempts, then the Observation is dropped with a logged warning — never an exception raised into the Agent's code.
- On shutdown, Transport performs a best-effort **Flush** with a short timeout, then stops regardless of whether the queue is empty.
- The Queue is **not** persisted to disk in V1.

---

## Rationale

### Blocking the Agent Is Unacceptable at Any Latency
If the SentinelX API takes 500ms to respond and the SDK waited synchronously, every single Agent Task would inherit that delay. This is not a marginal cost — it directly degrades the product the SDK is embedded inside, which is unacceptable for infrastructure meant to be invisible.

### Raising Exceptions on Network Failure Is Unacceptable at Any Rate
A transient network timeout is common and expected. If Transport propagated that as an exception, a routine network hiccup on SentinelX's side would crash a customer's production Agent — turning a security tool into a reliability liability, the opposite of its purpose.

### Not Every Failure Is Transient — Refined Under RC-8 (IDENTITY-002)
This ADR's core decision — async, passive, bounded retry — is unchanged. What was refined, and made explicit for the first time here, is *what counts as retry-worthy*: an authentication rejection (401/403) is not transient in the sense this ADR's own rationale describes — it will not resolve itself on attempt 2 or 3 with the same credential, so it fails fast instead of consuming the retry budget the "common and expected" transient case above was designed for. A rate-limit response (429) sits in between: retry-worthy, but on the Backend's own stated schedule (`Retry-After`) rather than this ADR's fixed backoff when the Backend provides one. See [`10-transport-layer.md`](../10-transport-layer.md#5-the-transport-lifecycle)'s Retry Policy for the full three-way classification.

### Why an In-Memory Queue, Not a Message Broker
The SDK runs embedded inside a single process — it is not a distributed system. Reaching for RabbitMQ or Kafka here would add real operational weight to solve a problem that exists at a scale of seconds and single-digit retry counts, not the scale those tools are built for.

### Why the Retry Count Is Fixed, Not Configurable
Retry behavior is an internal reliability concern, not a customer-facing tuning knob — exposing it would invite customers to reason about internals they shouldn't need to understand, contradicting the Public API's "Intent, not Implementation" principle (see [`04-public-api.md`](../04-public-api.md)).

### Why the SDK's Responsibility Explicitly Ends at 202 Accepted
See [`ADR-005-sdk-responsibility-boundary.md`](./ADR-005-sdk-responsibility-boundary.md) for the full reasoning — Transport's design here is the direct enabling mechanism for that boundary.

---

## Rejected Alternatives

| Alternative | Reason for Rejection |
|-------------|------------------------|
| Synchronous HTTP calls at the point an Observation completes | Directly ties Agent Task latency to network conditions outside the customer's control |
| Raising exceptions on delivery failure | Turns a routine network issue into a customer-facing Agent crash — violates the core Transport principle |
| A distributed message broker (RabbitMQ, Kafka) for the internal queue | Unjustified operational complexity for a single-process, in-memory workload |
| Configurable retry counts exposed to the customer | Exposes an internal reliability detail as a public decision the customer shouldn't need to make |
| Persisting the delivery queue to disk in V1 | Consistent with [`ADR-003-in-memory-observation-buffering.md`](./ADR-003-in-memory-observation-buffering.md) — a process crash already implies Agent failure, so the loss is accepted rather than engineered around |

---

## Consequences

- ✅ SentinelX outages, slow responses, or network partitions never propagate into customer Agent failures.
- ✅ Agent execution latency is unaffected by SentinelX's own response times.
- ✅ The internal Transport decomposition (Queue, Worker, Serializer, API Client — see [`10-transport-layer.md`](../10-transport-layer.md)) keeps each concern independently testable and independently replaceable.
- ⚠️ A small, accepted risk of Observation loss exists in two bounded scenarios: exceeding 3 retry attempts, and an incomplete flush during a fast shutdown. Both are deliberate, documented tradeoffs — not defects.
