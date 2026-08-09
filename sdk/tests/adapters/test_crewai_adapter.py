"""Tests for CrewAIAdapter, exercised against the real `crewai` package
(not a mock of its API).

Two things are tested separately, deliberately:

1. Registration/dispatch against the REAL, live crewai_event_bus (the
   officially supported Integration Point) -- proving start_listening()/
   stop_listening() correctly wire up and tear down against crewai's own
   mechanism. crewai's event bus carries process-global, cross-test
   "start/finish pairing" bookkeeping of its own (undocumented, and not
   cleared by its public reset_runtime_state()/scoped_handlers() APIs in
   every case) -- unrelated to this Adapter, but it means only one live
   dispatch is exercised per process here to avoid fighting that state.

2. Translation correctness for every event type -- calling each private
   handler directly with a genuine crewai Pydantic event instance. This
   is still "real crewai data," just without depending on crewai's
   stateful global bus for dispatch, which keeps these tests fast,
   deterministic, and independent of crewai's internal bookkeeping.
"""

import pytest

crewai = pytest.importorskip("crewai", reason="crewai is an optional dependency (pip install ases[crewai])")

from crewai.events import crewai_event_bus  # noqa: E402
from crewai.events.types.llm_events import LLMCallStartedEvent  # noqa: E402
from crewai.events.types.task_events import TaskCompletedEvent, TaskFailedEvent, TaskStartedEvent  # noqa: E402
from crewai.events.types.tool_usage_events import ToolUsageFinishedEvent, ToolUsageStartedEvent  # noqa: E402
from crewai.tasks.task_output import TaskOutput  # noqa: E402

from ases.adapters.crewai.adapter import CrewAIAdapter  # noqa: E402


def _make_adapter():
    adapter = CrewAIAdapter()
    received = []
    adapter._bind(lambda signal: received.append(signal))
    return adapter, received


# --- 1. Real, live crewai_event_bus dispatch ---------------------------

def test_registers_and_dispatches_via_the_real_event_bus():
    with crewai_event_bus.scoped_handlers():
        adapter, received = _make_adapter()
        adapter.start_listening()

        crewai_event_bus.emit(
            source=None, event=TaskStartedEvent(context="review invoices", task_id="task-1")
        )

    assert len(received) == 1
    assert received[0].event_type == "custom"
    assert received[0].runtime_context == {"crewai_task_id": "task-1"}


def test_stop_listening_unregisters_from_the_real_event_bus():
    with crewai_event_bus.scoped_handlers():
        adapter, received = _make_adapter()
        adapter.start_listening()
        adapter.stop_listening()

        crewai_event_bus.emit(source=None, event=TaskStartedEvent(context="x", task_id="task-2"))

    assert received == []  # nothing forwarded -- handlers were unregistered


# --- 2. Translation correctness, per event type -------------------------
# Each handler is called directly with a genuine crewai event instance --
# real fields, real types, just without going through crewai's stateful
# global bus dispatch.

def test_translates_task_started():
    adapter, received = _make_adapter()
    event = TaskStartedEvent(context="review invoices", task_id="task-1")
    adapter._on_task_started(None, event)

    assert len(received) == 1
    assert received[0].event_type == "custom"
    assert received[0].payload["crewai_event"] == "task_started"
    assert received[0].runtime_context == {"crewai_task_id": "task-1"}


def test_translates_tool_usage_started():
    adapter, received = _make_adapter()
    event = ToolUsageStartedEvent(tool_name="search", tool_args={"query": "AI security"}, task_id="task-2")
    adapter._on_tool_usage_started(None, event)

    assert received[0].event_type == "tool_execution"
    assert received[0].payload == {
        "tool": "search",
        "args": {"query": "AI security"},
        "crewai_event": "tool_usage_started",
    }
    assert received[0].runtime_context == {"crewai_task_id": "task-2"}


def test_translates_tool_usage_finished():
    import datetime

    adapter, received = _make_adapter()
    event = ToolUsageFinishedEvent(
        tool_name="search",
        tool_args={},
        task_id="task-2",
        started_at=datetime.datetime.now(),
        finished_at=datetime.datetime.now(),
        output="ok",
        from_cache=False,
    )
    adapter._on_tool_usage_finished(None, event)

    assert received[0].event_type == "tool_execution"
    assert received[0].payload == {
        "tool": "search",
        "from_cache": False,
        "crewai_event": "tool_usage_finished",
    }


def test_translates_llm_call_started():
    adapter, received = _make_adapter()
    event = LLMCallStartedEvent(call_id="call-1", model="gpt-4o", task_id="task-2")
    adapter._on_llm_call_started(None, event)

    assert received[0].event_type == "custom"
    assert received[0].payload == {"model": "gpt-4o", "crewai_event": "llm_call_started"}


def test_translates_task_completed_to_observation_completed():
    adapter, received = _make_adapter()
    output = TaskOutput(description="reviewed invoices", agent="Finance Assistant")
    event = TaskCompletedEvent(output=output, task_id="task-3")
    adapter._on_task_completed(None, event)

    assert received[0].signal_type == "OBSERVATION_COMPLETED"
    assert received[0].reason == "framework_task_finished"
    assert received[0].runtime_context == {"crewai_task_id": "task-3"}


def test_translates_task_failed_to_observation_completed():
    adapter, received = _make_adapter()
    event = TaskFailedEvent(error="boom", task_id="task-4")
    adapter._on_task_failed(None, event)

    assert received[0].signal_type == "OBSERVATION_COMPLETED"
    assert received[0].reason == "framework_task_finished"


def test_different_tasks_produce_different_runtime_context():
    adapter, received = _make_adapter()
    adapter._on_task_started(None, TaskStartedEvent(context="a", task_id="task-6"))
    adapter._on_task_started(None, TaskStartedEvent(context="b", task_id="task-7"))

    assert received[0].runtime_context != received[1].runtime_context


def test_emit_before_attach_raises_runtime_error():
    adapter = CrewAIAdapter()
    with pytest.raises(RuntimeError):
        adapter._on_task_started(None, TaskStartedEvent(context="a", task_id="task-8"))
