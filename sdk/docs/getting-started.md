# Getting Started

Five steps, following the same Customer Journey documented in
`05-customer-integration-journey.md`.

## 1. Install

```bash
pip install ases
```

## 2. Get an API Key

Register your Agent in the SentinelX Dashboard (Name, Framework — choose
"Custom Python Agent" for a Django-based Agent — Version, Environment).
SentinelX issues an Agent API Key.

## 3. Configure

Configure once, at application startup — never repeated per-request. For a
Django project, `AppConfig.ready()` (in your app's `apps.py`) or the bottom
of `settings.py` are both reasonable places:

```python
from ases import configure

configure(api_key="ases_xxxxxxxxx")
```

Or, equivalently, set the environment variable and skip the explicit call:

```bash
export ASES_API_KEY=ases_xxxxxxxxx
```

## 4. Attach an Adapter and Start

### CrewAI

```bash
pip install ases[crewai]
```

```python
from crewai import Crew
from ases import ASES
from ases.adapters import CrewAIAdapter

crew = Crew(...)

ases = ASES()
adapter = CrewAIAdapter(crew)
ases.attach(adapter)
ases.start()

crew.kickoff()

ases.stop()
```

No changes to the Crew, Agents, or Tasks themselves — `CrewAIAdapter`
listens on crewai's own event bus (its official Integration Point) and
translates `TaskStarted`, `ToolUsageStarted/Finished`, `LLMCallStarted/
Completed`, and `TaskCompleted`/`TaskFailed` into ASES Events automatically.
See [`examples/crewai-basic/`](../examples/crewai-basic/) for a version
runnable with zero external services.

### Custom / Django Agents (Manual API)

```python
from ases import ASES
from ases.adapters import GenericAdapter

ases = ASES()
adapter = GenericAdapter()
ases.attach(adapter)
ases.start()
```

Keep `ases` and `adapter` as long-lived module-level objects (as in the
Django example) — do not recreate them per-request.

## 5. Instrument Your Agent's Code

Call `emit()` at the points in your own code where something worth
observing happens — a Tool call, an LLM call, a file access, an outbound API
call. You never build JSON, never touch HTTP, and never modify your Agent's
actual business logic — only add these calls around it.

### Simple, single-execution Agents

```python
adapter.emit("tool_execution", {"tool": "search", "query": "..."})
adapter.emit("custom", {"kind": "llm_call", "model": "gpt-4o", "tokens": 812})
adapter.complete()
```

`event_type` must be one of the Backend's ten canonical Event Dictionary values (`api_call`, `file_access`, `command_execution`, `network_connection`, `database_operation`, `tool_execution`, `memory_operation`, `authentication`, `configuration_change`, `custom`) — `emit()` raises a clear `AdapterError` immediately if given anything else.

### Concurrent Agents (recommended for Django views)

Each request gets its own correlated `Execution`, so Observations from
different requests are never mixed together
(`09-observation-lifecycle.md`, "Can Multiple Observations Exist at Once?
Yes, definitely."):

```python
def my_view(request):
    execution = adapter.begin_execution()
    try:
        execution.emit("tool_execution", {"tool": "search", "query": "..."})
        # ... your existing business logic, unchanged ...
        execution.complete()
    except Exception:
        execution.complete()  # close the Observation even on failure
        raise
```

## What Happens Next

Every `emit()` call groups automatically into a single Observation per
execution. Once `complete()` is called (or your Agent finishes, or 30
seconds pass with no new Event, or `ases.stop()` runs), the Observation is
built, validated, and delivered to SentinelX in the background — your
Agent's own execution is never delayed waiting for that delivery
(`10-transport-layer.md`).

Open the SentinelX Dashboard to see the result: Observation → Prediction →
Risk Score → Alert (if any).
