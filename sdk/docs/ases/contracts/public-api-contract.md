# Contract: Public API

> This is an implementation-ready specification, derived directly from [`04-public-api.md`](../04-public-api.md) and [`11-repository-architecture.md`](../11-repository-architecture.md). Anything not listed here must not be exposed at the package root without a new ADR.

---

## 1. Root-Level Public Surface

Exposed via `ases/__init__.py`. This is the **entire** customer-facing API:

```python
from ases import ASES
from ases import configure
from ases import monitor
from ases import shutdown
```

No other import path is considered public. Internal paths (e.g., `ases.transport.worker`) are implementation detail and may change between minor versions without notice.

`ASES` and `configure` are specified in sections 2 and 5 below; `monitor` and `shutdown` are specified in section 8 — all four are part of one reconciled surface (see [`04-public-api.md §6a`](../04-public-api.md#6a-the-fast-path--monitor--configure--shutdown)), not two competing ones.

---

## 2. The `ASES` Class

| Method | Signature | Behavior |
|--------|-----------|----------|
| Constructor | `ASES(api_key: str)` | Creates an SDK instance. Minimal constructor — no configuration object in V1 (see [`04-public-api.md`](../04-public-api.md#5-configuration-shape)). |
| `attach` | `attach(adapter: Adapter) -> None` | Registers an Adapter instance with this SDK. May be called multiple times to register multiple Adapters. Must be called before `start()`. |
| `start` | `start() -> None` | Begins accepting Events from all attached Adapters. Explicit and idempotent — calling it while already started is a no-op, not an error. Runs in-process; does not spawn a new process or thread of its own. |
| `stop` | `stop() -> None` | Stops accepting new Events and triggers Transport's Flush behavior (see [`10-transport-layer.md`](../10-transport-layer.md#5-the-transport-lifecycle)). |

---

## 3. The Adapter Contract

Every Adapter — whether shipped (`CrewAIAdapter`, `LangGraphAdapter`) or customer-facing (`GenericAdapter`) — must satisfy exactly this interface:

| Method | Required | Behavior |
|--------|----------|----------|
| `start_listening()` | ✅ | Begin listening for framework events. Called internally when `ases.start()` runs. |
| `stop_listening()` | ✅ | Stop listening for framework events. Called internally when `ases.stop()` runs. |
| *(internal)* forward to SDK | ✅ | Every observed occurrence must be forwarded into the SDK as one of the two signals defined in [`adapter-signal-contract.md`](./adapter-signal-contract.md). |

An Adapter must **never**:
```text
✘ Construct an Observation object directly.
✘ Perform an HTTP request.
✘ Import or reference the Transport layer.
✘ Implement retry logic.
```

---

## 4. `GenericAdapter` — the Manual API

For Agents with no supported framework (see [`03-agent-integration-models.md`](../03-agent-integration-models.md#5-model-4--manual-api-generic-fallback)):

```python
from ases.adapters import GenericAdapter

adapter = GenericAdapter()
ases.attach(adapter)
ases.start()

adapter.emit(
    event_type: str,
    payload: dict
) -> None
```

`emit()` is the only method a customer calls directly on this Adapter. Internally, it is translated into the same `EVENT` signal every other Adapter produces — see [`adapter-signal-contract.md`](./adapter-signal-contract.md).

---

## 5. `configure()` — Module-Level Configuration

```python
from ases import configure

configure(api_key: str = None) -> None
```

Called at most once, before any `ASES(...)` instance is constructed, if a customer prefers module-level configuration over passing `api_key` directly to the constructor. See [`environment-configuration.md`](./environment-configuration.md) for the full precedence rules between this call, the constructor argument, and environment variables.

---

## 6. What Is Explicitly NOT in the Public API (V1)

```text
✘ build_observation()
✘ send()
✘ serialize()
✘ Any direct access to Observation, Event, or Metadata model classes
✘ Any direct access to Queue, Worker, Serializer, or API Client
✘ Retry configuration
✘ Endpoint override via the constructor (env var only — see environment-configuration.md)
```

If a future requirement seems to need one of these exposed, it must go through a new ADR — not be added quietly.

---

## 7. Complete Reference Example (Full-Control Path)

```python
from crewai import Crew
from ases import ASES
from ases.adapters import CrewAIAdapter

crew = Crew(...)

ases = ASES(api_key="sk_live_ab12_9f8e7d6c5b4a3928...")
adapter = CrewAIAdapter(crew)
ases.attach(adapter)
ases.start()

crew.kickoff()

ases.stop()
```

---

## 8. `monitor()` / `configure()` / `shutdown()` — the Fast Path

Thin convenience wrappers over the `ASES` class (section 2) — not a second, competing implementation. See [`04-public-api.md §6a`](../04-public-api.md#6a-the-fast-path--monitor--configure--shutdown) for the design rationale.

| Function | Signature | Behavior |
|--------|-----------|----------|
| `monitor` | `monitor(adapter: Adapter, api_key: str = None) -> ASES` | Constructs an `ASES` instance (using `api_key` if given, otherwise falling back to a prior `configure()` call or the `ASES_API_KEY` environment variable, per the same precedence rules `ASES(...)` itself uses — see [`environment-configuration.md §3`](./environment-configuration.md)), attaches exactly one Adapter, starts it, and returns the instance. Does not support attaching more than one Adapter — use the `ASES` class directly for that case. |
| `shutdown` | `shutdown() -> None` | Stops whatever instance `monitor()` most recently created. A no-op, not an error, if `monitor()` was never called — mirrors `start()`'s own documented idempotency (section 2). |

`configure()` is already fully specified in section 5, above; it is listed here only to confirm it is part of the same fast path as `monitor()`/`shutdown()`, not a third, unrelated surface.

Complete fast-path example:

```python
from ases import monitor
from ases.adapters import CrewAIAdapter

monitor(CrewAIAdapter(crew))

crew.kickoff()
```
