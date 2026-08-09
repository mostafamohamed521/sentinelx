# Architecture

This build implements the pipeline documented in `08-internal-architecture.md`
end to end:

```text
Your Agent's code (Django view, service, etc.)
    │
    ▼
GenericAdapter.emit() / Execution.emit()      ases/adapters/generic/adapter.py
    │
    ▼
Dispatcher                                     ases/pipeline/dispatcher.py
    │
    ▼
ObservationCollector                           ases/observation/collector.py
    │  (groups Events by runtime_context, owns the Lifecycle state machine)
    ▼
build_observation()                            ases/observation/builder.py
    │  (stateless — runs once, after collection completes)
    ▼
validate_observation()                         ases/observation/validator.py
    │
    ▼
Transport.enqueue()                            ases/transport/__init__.py
    │
    ▼
ObservationQueue → Worker → serialize_observation() → APIClient.send()
                                                        ases/transport/{queue,worker,serializer,client}.py
    │
    ▼
SentinelX REST API  (POST {endpoint}/api/v1/observations → 202 Accepted)
```

## Where Framework-Specific Logic Lives

Per ADR-001, the Core above never changes when a new framework is
supported. `CrewAIAdapter` (`ases/adapters/crewai/adapter.py`) is the
proof: it listens on crewai's own process-wide event bus
(`crewai.events.crewai_event_bus`) — crewai's official Integration Point —
and translates `TaskStarted`, `ToolUsageStarted/Finished`,
`LLMCallStarted/Completed`, and `TaskCompleted`/`TaskFailed` into the same
two signals (`EventSignal`, `ObservationCompletedSignal`) that
`GenericAdapter` produces manually. A future LangGraph or OpenAI Agents SDK
Adapter would follow the identical pattern, at
`ases/adapters/langgraph/adapter.py` / `ases/adapters/openai_agents/adapter.py`.
Nothing in `pipeline/`, `observation/`, `transport/`, or `ases/__init__.py`
needs to change either way.

`crewai` itself is an *optional* dependency (`pip install ases[crewai]`),
imported lazily (`ases/adapters/__init__.py`) so the Core and
`GenericAdapter` remain usable with zero third-party dependencies.

## Threading Model

- The **Observation Collector** runs a daemon thread that polls once a
  second for timed-out Observations (30s of inactivity).
- The **Transport Worker** runs a second daemon thread that drains the
  in-memory Queue and performs the (blocking, but backgrounded) HTTP calls.
- Your Agent's own thread only ever touches `emit()`/`complete()`, which are
  non-blocking, in-memory operations — network I/O never happens on your
  Agent's own call stack.

## Two Explicitly Flagged Assumptions

See `README.md`, "Scope of This Release", for the two points (wire-format
schema, endpoint/auth contract) that were reconstructed from documentation
rather than confirmed against the live Laravel backend. Both are isolated
to a small, named set of files by design.
