# Glossary

> Every term used across this documentation, defined exactly once.

---

**ASES Integration Layer**
The full client-side component connecting AI Agent frameworks to SentinelX. Deliberately not called "the SDK" in this documentation, to avoid implying it's a general-purpose monitoring tool. See [`01-overview.md`](./01-overview.md).

**`ASES` (class)**
The engine underlying the entire Public API: constructor, `attach()`, `start()`, `stop()`. The full-control path. See [`04-public-api.md`](./04-public-api.md).

**`monitor()` / `configure()` / `shutdown()`**
Thin convenience wrappers over the `ASES` class, for the common single-Adapter case. The fast path. See [`04-public-api.md §6a`](./04-public-api.md#6a-the-fast-path--monitor--configure--shutdown).

**Observation**
The complete execution story of a single Agent Task — not a single Event, and not a snapshot. Built from one or more correlated Events. See [`09-observation-lifecycle.md`](./09-observation-lifecycle.md).

**Event**
A single, discrete occurrence inside an Agent's execution (a Tool call, an LLM call, a Node transition), normalized into the Canonical Event Model regardless of source framework. See [`01-overview.md`](./01-overview.md).

**Canonical Event Model**
The single, framework-neutral shape (`{ "event_type": ..., "payload": ... }`) that every Event is converted into, regardless of how the source framework originally described it. See [`01-overview.md`](./01-overview.md).

**Integration Point**
The official extension mechanism a framework or application exposes, allowing an outside system to observe execution without modifying business logic. Without one, integration is not possible. See [`06-integration-point-concept.md`](./06-integration-point-concept.md).

**Integration Model**
One of four mechanical patterns (Callback, Middleware, Decorator, Manual API) an Adapter can use to receive data through an Integration Point. See [`03-agent-integration-models.md`](./03-agent-integration-models.md).

**Callback**
An Integration Point shape where the framework itself calls out to notify ASES that something happened. V1's primary Integration Model. See [`03-agent-integration-models.md`](./03-agent-integration-models.md) and [`06-integration-point-concept.md`](./06-integration-point-concept.md).

**Hook**
An Integration Point shape where the framework pauses execution before a step and allows external code to run. See [`06-integration-point-concept.md`](./06-integration-point-concept.md).

**Middleware**
An Integration Point shape where every request/response passes through a shared interception layer. Deferred to V2. See [`03-agent-integration-models.md`](./03-agent-integration-models.md).

**Wrapper**
An Integration Point shape where ASES wraps an existing object without altering its behavior. See [`06-integration-point-concept.md`](./06-integration-point-concept.md).

**Manual API**
The Generic Adapter's `emit(...)` fallback, for Agents with no framework-provided Integration Point. V1 scope. See [`03-agent-integration-models.md`](./03-agent-integration-models.md).

**Adapter**
The only component with direct visibility into a specific framework's execution. Deliberately "thin" — emits only `EVENT` and `OBSERVATION_COMPLETED` signals, never builds an Observation, never performs network I/O. See [`08-internal-architecture.md`](./08-internal-architecture.md) and [`ADR-002-thin-adapter-principle.md`](./adr/ADR-002-thin-adapter-principle.md).

**Event Pipeline**
The framework-agnostic entry point every Adapter's output passes through; a pure Orchestrator that routes Events onward without building or transporting anything. See [`08-internal-architecture.md`](./08-internal-architecture.md).

**Observation Collector**
The component responsible for gathering related Events, tracking the Observation Lifecycle state machine, and deciding when an Observation is complete. See [`09-observation-lifecycle.md`](./09-observation-lifecycle.md).

**Observation Builder**
The first component in the pipeline that knows the ASES Schema. Stateless — runs exactly once, after the Collector finishes gathering Events, converting them into an ASES Observation. See [`08-internal-architecture.md`](./08-internal-architecture.md).

**Observation Validator**
Confirms a built Observation complies with the ASES Schema before Transport ever sees it; rejects malformed Observations. See [`08-internal-architecture.md`](./08-internal-architecture.md).

**Transport**
The component responsible for everything network-related: Queue, Worker, Serializer, and API Client. Asynchronous and Passive by design — must never block or crash the host Agent. See [`10-transport-layer.md`](./10-transport-layer.md).

**Runtime Context**
Internal-only correlation data an Adapter attaches to its signals, allowing the Collector to group Events belonging to the same in-flight Observation — especially when multiple Observations run concurrently. Never exposed in the outward-facing ASES JSON. See [`09-observation-lifecycle.md`](./09-observation-lifecycle.md) and [`contracts/adapter-signal-contract.md`](./contracts/adapter-signal-contract.md).

**EVENT (signal)**
One of the two signal types an Adapter may emit — indicates a single observed occurrence. See [`contracts/adapter-signal-contract.md`](./contracts/adapter-signal-contract.md).

**OBSERVATION_COMPLETED (signal)**
The second of the two signal types an Adapter may emit — indicates the Adapter has detected that execution has ended. See [`contracts/adapter-signal-contract.md`](./contracts/adapter-signal-contract.md).

**Execution Framework**
Software that controls an AI Agent's execution journey (e.g., CrewAI, LangGraph, AutoGen) — distinct from an LLM Model provider (e.g., OpenAI, Anthropic). Adapters are built for Execution Frameworks, never for Models directly. See [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md).

**Embedded Security (market)**
The market this layer is built for: companies installing a security layer inside an AI Agent application they themselves own or develop. See [`05-customer-integration-journey.md`](./05-customer-integration-journey.md).

**External Monitoring (market)**
A distinct, out-of-scope market (proxies, browser extensions, desktop/OS-level monitoring) that this layer deliberately does not serve. See [`05-customer-integration-journey.md`](./05-customer-integration-journey.md).

**Desktop Sensor**
A distinct, future product concept (not part of this layer) that could observe closed-source AI applications like Claude Code or Cursor at the OS/desktop level, rather than via an embedded SDK. Explicitly filed under Future Products. See [`07-agent-framework-ecosystem.md`](./07-agent-framework-ecosystem.md).

**Vertical Slice**
An implementation strategy: building one thin, complete, end-to-end runnable path (even supporting only one Event type) before broadening coverage — rather than fully completing each architectural layer in isolation before connecting them. See [`13-implementation-roadmap.md`](./13-implementation-roadmap.md).

**202 Accepted (SDK responsibility boundary)**
The HTTP response that marks the exact point where SDK responsibility for a given Observation ends; everything downstream (ML analysis, Prediction, Alerting) is entirely server-side and never tracked by the SDK. See [`10-transport-layer.md`](./10-transport-layer.md) and [`ADR-005-sdk-responsibility-boundary.md`](./adr/ADR-005-sdk-responsibility-boundary.md).
