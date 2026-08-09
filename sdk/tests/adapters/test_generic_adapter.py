import pytest

from ases.adapters.generic.adapter import GenericAdapter
from ases.shared.exceptions import AdapterError


def test_emit_before_attach_raises():
    adapter = GenericAdapter()
    with pytest.raises(RuntimeError):
        adapter.emit("tool_execution", {"tool": "search"})


def test_emit_and_complete_forward_signals():
    received = []
    adapter = GenericAdapter()
    adapter._bind(lambda signal: received.append(signal))

    adapter.emit("tool_execution", {"tool": "search"})
    adapter.emit("custom", {"model": "gpt-4o"})
    adapter.complete()

    assert len(received) == 3
    assert received[0].event_type == "tool_execution"
    assert received[0].framework == "generic"
    assert received[2].signal_type == "OBSERVATION_COMPLETED"


def test_emit_rejects_non_canonical_event_type():
    adapter = GenericAdapter()
    adapter._bind(lambda signal: None)
    with pytest.raises(AdapterError):
        adapter.emit("tool_call", {"tool": "search"})


def test_complete_without_any_emit_raises():
    adapter = GenericAdapter()
    adapter._bind(lambda signal: None)
    with pytest.raises(AdapterError):
        adapter.complete()


def test_begin_execution_allows_concurrent_tasks_with_distinct_correlation():
    received = []
    adapter = GenericAdapter()
    adapter._bind(lambda signal: received.append(signal))

    exec_a = adapter.begin_execution()
    exec_b = adapter.begin_execution()
    exec_a.emit("tool_execution", {"tool": "a"})
    exec_b.emit("tool_execution", {"tool": "b"})
    exec_a.complete()
    exec_b.complete()

    assert received[0].runtime_context != received[1].runtime_context


def test_emit_after_complete_on_same_execution_raises():
    adapter = GenericAdapter()
    adapter._bind(lambda signal: None)
    execution = adapter.begin_execution()
    execution.emit("tool_execution", {"tool": "a"})
    execution.complete()
    with pytest.raises(AdapterError):
        execution.emit("tool_execution", {"tool": "b"})
