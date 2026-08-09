# 08 — Internal Architecture

> Source concept: Session 5 — the first session where design shifts from "why does this layer exist" to "how is it actually built." This document lays out the internal processing pipeline that everything else in this layer runs through.

---

## 1. The Question That Orders Every Component

> **When the Agent produces an Event, who finds out about it first?**

Not the SDK's Core. Not the Observation Builder. Not the API Client. The answer is the **Adapter**, because it is the only component that actually lives inside the framework's execution.

```text
First Component: Adapter
```

---

## 2. Component 1 — Adapter

> **The Adapter should do the least possible amount of work. This is an architectural rule, not a suggestion.**

An Adapter must never build an Observation, never perform an HTTP request, never know about the ASES API, and never implement retry logic. Its entire job is to notice that something happened and say so.

```text
Responsibility: "I saw an Event." — nothing more.
```

The right mental model: an Adapter is a **microphone**. It transmits sound; it does not interpret it.

---

## 3. Component 2 — Event Pipeline

Once the Adapter emits something, it needs somewhere to go — that destination is the **Event Pipeline**, arguably the heart of the whole layer, because *every* Event, regardless of origin, passes through it.

```text
CrewAI       → Pipeline
LangGraph      → Pipeline
Custom Agent    → Pipeline
```

This is what makes the rest of the system independent of any specific framework. And the Pipeline itself does very little:

```text
Receive Event
    ↓
Confirm it's valid
    ↓
Forward it onward
```

It is an **Orchestrator** — specifically not a Builder, not a Serializer, not a Transport mechanism.

---

## 4. Component 3 — Observation Builder

> **Who builds the Observation? The Observation Builder** — the first component in this pipeline that actually knows the ASES Schema.

This matters precisely because the Adapter does **not** know the JSON shape, and does not know the Event Dictionary — it only knows its framework. The Builder is the opposite: it knows the Standard, and nothing about any particular framework. This is a clean, deliberate separation of concerns.

---

## 5. Component 4 — Observation Validator

After an Observation is built, it needs to be checked — a Timestamp could be missing, an Event could be malformed, Metadata could be incomplete. Before anything is transmitted, the Validator confirms correctness — including that `metadata.spec_version` and `metadata.sdk_version` are both present (previously unspecified here; see `01-overview.md §4.2` for the complete Metadata shape, `PIPELINE-005`).

---

## 6. Component 5 — Transport

What happens after validation is treated, at this stage of design, as a **black box** — a full session in its own right (see [`10-transport-layer.md`](./10-transport-layer.md)):

```text
Observation → Transport → SentinelX API
```

---

## 7. What Is a Service vs. a Component?

Three concerns exist outside the processing pipeline entirely, as shared **Services** — read by every component, but never mutated by any of them:

```text
Configuration   — a Service. Every component reads it; none of them modify it.
Logger           — a Service. Not part of the Pipeline.
Authentication    — a Service.
API Client         — a Service.
Retry               — lives inside Transport, not the Core.
```

Keeping Configuration and Logging as shared, read-only Services (rather than scattering environment-variable access across every class) is exactly what prevents the chaos that comes from a dozen components each managing their own version of shared state.

**The Logger must never log the API Key** — not the raw value, and not a larger object (e.g. the full `Settings` instance) that happens to contain it. This applies everywhere the Logger is used, including the authentication-failure warning Transport logs on a 401/403 (`10-transport-layer.md §6`'s Retry Policy) — that message states *that* authentication failed, never the credential that failed (RC-8, IDENTITY-004). Stated with the same explicit "must never" discipline this document set already uses for the Adapter Contract (`contracts/public-api-contract.md §3`) and the Adapter Signal Contract (`contracts/adapter-signal-contract.md §5`) — this was previously an implicit assumption, not a stated rule.

---

## 8. The Architecture, Visualized

```text
Framework
    │
    ▼
CrewAI Adapter
    │
    ▼
Event Pipeline
    │
    ▼
Observation Builder
    │
    ▼
Observation Validator
    │
    ▼
Transport
    │
    ▼
SentinelX REST API
```

Every layer does exactly one thing — **Single Responsibility**, applied structurally, not just as an aspiration.

### The Test That Proves It Works

If LangGraph support is added tomorrow, what changes?

```text
CrewAI Adapter  →  becomes  →  LangGraph Adapter
```

Everything else: **zero lines changed.** That is the actual, concrete success criterion this architecture is judged against.

---

## 9. The Missing Component: Observation Collector

Drawing the pipeline out loud surfaced a gap. An Adapter sends one Event, then another, then another — but the ML pipeline expects a complete **Observation**, not a stream of individual Events. Something has to be responsible for grouping them.

```text
Event 1 + Event 2 + Event 3 + ... = Observation
```

That responsibility belongs to a distinct component — the **Observation Collector** — genuinely different from the Builder, the Adapter, or anything else already defined. This gap wasn't visible until the full customer journey (see [`05-customer-integration-journey.md`](./05-customer-integration-journey.md)) had already been walked through — a good example of why product-level thinking has to happen before internal architecture is finalized.

---

## 10. The Final Six-Component Chain

```text
Adapter        →  sees an Event.
Pipeline         →  routes an Event.
Collector          →  gathers Events.
Builder              →  builds an Observation.
Validator              →  confirms correctness.
Transport                →  sends it.
```

Exactly **when** the Collector decides an Observation is complete is the hardest engineering question in this entire layer, and it's significant enough to deserve its own dedicated document: [`09-observation-lifecycle.md`](./09-observation-lifecycle.md).

---

## 11. Summary

```text
Internal Architecture

Framework
    ↓
Adapter
    ↓
Event Pipeline
    ↓
Observation Collector
    ↓
Observation Builder
    ↓
Observation Validator
    ↓
Transport
    ↓
SentinelX API

────────────────────────

Components

Adapter
- Receives framework events.
- Knows framework APIs only.
- Never builds observations.
- Never performs HTTP requests.

Event Pipeline
- Entry point for all incoming events.
- Routes events through the SDK.
- Framework agnostic.

Observation Collector
- Groups related events.
- Maintains observation lifecycle.
- Decides when an observation is complete.

Observation Builder
- Converts collected events into an ASES Observation.
- Knows the ASES Schema.
- Framework independent.

Observation Validator
- Ensures the generated observation complies with the schema.
- Rejects malformed observations.

Transport
- Sends observations to SentinelX.
- Handles retries and network communication.

────────────────────────

Shared Services (outside the pipeline)
- Configuration
- Logger
- API Client
- Authentication

────────────────────────

Design Principles
- Single Responsibility Principle.
- Framework Agnostic Core.
- Thin Adapters.
- Modular Pipeline.
```

---

## 12. A Small Naming Discipline With a Large Payoff

Nowhere in this architecture does the word **"Manager"** appear — deliberately. Classes like `SDKManager`, `EventManager`, `ObservationManager`, or `PipelineManager` are one of the most reliable ways an architecture quietly degrades over time, because a name like "Manager" hides responsibility rather than clarifying it.

Every component in this layer is instead named for exactly what it does:

```text
Adapter · Collector · Builder · Validator · Transport
```

A new engineer reading this codebase a year from now should be able to understand the entire architecture within five minutes, purely from these names — and that is a direct, intended consequence of this naming discipline, not a lucky accident.
