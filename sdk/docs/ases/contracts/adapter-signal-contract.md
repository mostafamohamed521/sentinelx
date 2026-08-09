# Contract: Adapter Signals

> This is an implementation-ready specification, derived directly from [`09-observation-lifecycle.md`](../09-observation-lifecycle.md) and [`ADR-002-thin-adapter-principle.md`](../adr/ADR-002-thin-adapter-principle.md). Every Adapter — shipped or third-party — must communicate with the Event Pipeline using exactly these two signal types, and nothing else.

---

## 1. The Two Signal Types

```text
EVENT
OBSERVATION_COMPLETED
```

No other signal type is defined or permitted in V1.

---

## 2. `EVENT` Signal

Emitted every time the Adapter observes a discrete occurrence inside the framework (a Tool call, an LLM call, a Node execution, etc.).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `signal_type` | `"EVENT"` | ✅ | Fixed value identifying this signal type |
| `event_type` | string | ✅ | One of the Backend's ten canonical Event Dictionary values (`api_call`, `file_access`, `command_execution`, `network_connection`, `database_operation`, `tool_execution`, `memory_operation`, `authentication`, `configuration_change`, `custom`) — never the framework's own internal terminology verbatim. Enforced at construction: constructing an `EventSignal` with any other value raises an error (RC-7, Ground 2) |
| `payload` | object | ✅ | Arbitrary, event-specific data. Shape is defined by the Canonical Event Model, not by the Adapter |
| `runtime_context` | object | ✅ | Internal correlation data (see Section 4) — **never** included in the outward-facing ASES JSON |
| `timestamp` | ISO 8601 string | ✅ | When the Adapter observed this occurrence |
| `framework` | string | ✅ | Identifies which framework/Adapter produced this signal (e.g. `"crewai"`, `"generic"`) — becomes the Observation's wire-format `context.framework` field |

### Example

```json
{
  "signal_type": "EVENT",
  "event_type": "tool_execution",
  "payload": {
    "tool": "search",
    "query": "latest AI security news"
  },
  "runtime_context": {
    "execution_id": "internal-crewai-run-object-ref"
  },
  "timestamp": "2026-08-01T10:15:32Z",
  "framework": "crewai"
}
```

### 2a. Ordering and the `sequence` Field

No explicit per-event `sequence` field is part of this signal, or of the final wire-format Event's `header`. Ordering is guaranteed instead by array position within the Observation's `events` array — the Collector appends Events in the order it receives them, and the Builder preserves that order when constructing the final JSON (09-observation-lifecycle.md §7: one build, at the end, never incrementally). Both the Backend's `ObservationValidator` and the ML Service rely on array position, not a `sequence` field, for ordering — this was previously undocumented (`PIPELINE-003`) and is stated explicitly here to close that gap.

---

## 3. `OBSERVATION_COMPLETED` Signal

Emitted when the Adapter detects that the framework has finished the current unit of execution — via any of the four terminating conditions in [`09-observation-lifecycle.md`](../09-observation-lifecycle.md#3-when-does-it-end-the-harder-half-of-the-question).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `signal_type` | `"OBSERVATION_COMPLETED"` | ✅ | Fixed value identifying this signal type |
| `runtime_context` | object | ✅ | Must match the `runtime_context` of the Events being closed out, so the Collector can correlate correctly |
| `reason` | string | ✅ | One of: `"framework_task_finished"`, `"agent_execution_ended"`, `"timeout"`, `"sdk_shutdown"` |
| `timestamp` | ISO 8601 string | ✅ | When the Adapter detected completion |

### Example

```json
{
  "signal_type": "OBSERVATION_COMPLETED",
  "runtime_context": {
    "execution_id": "internal-crewai-run-object-ref"
  },
  "reason": "framework_task_finished",
  "timestamp": "2026-08-01T10:15:41Z"
}
```

---

## 4. `runtime_context` — Internal Only, Never Wire Format

> **This field must never appear in the ASES JSON Observation sent to the Backend.**

Its sole purpose is enabling the Observation Collector to correlate multiple `EVENT` signals — and their eventual `OBSERVATION_COMPLETED` — with the same in-flight Observation, especially when multiple Observations run concurrently (see [`09-observation-lifecycle.md`](../09-observation-lifecycle.md#4-can-multiple-observations-exist-at-once)). Its shape is Adapter-specific and opaque to every other component — the Collector only needs it to be a stable, comparable value per execution, not to understand its internal structure.

---

## 5. What an Adapter Must NOT Emit

```text
✘ Any signal type other than EVENT or OBSERVATION_COMPLETED.
✘ A fully-formed Observation object.
✘ Framework-specific terminology as the event_type value (e.g., raw CrewAI internal names).
✘ HTTP requests of any kind.
```

---

## 6. Consumers of These Signals

Both signal types are consumed exclusively by the **Observation Collector** (see [`08-internal-architecture.md`](../08-internal-architecture.md#3-component-3--observation-builder) and [`09-observation-lifecycle.md`](../09-observation-lifecycle.md)) via the Event Pipeline. No other component — including the Observation Builder — ever receives raw Adapter signals directly.
