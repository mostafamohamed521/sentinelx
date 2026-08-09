import pytest

from ases.observation.builder import build_observation
from ases.pipeline.events import EventSignal


def _make_signal(event_type="tool_execution", timestamp="2026-01-01T00:00:00Z"):
    return EventSignal(
        event_type=event_type,
        payload={"k": "v"},
        runtime_context={"execution_id": "abc"},
        timestamp=timestamp, framework="generic")


def test_build_observation_from_multiple_events():
    signals = [
        _make_signal("tool_execution", "2026-01-01T00:00:00Z"),
        _make_signal("custom", "2026-01-01T00:00:05Z"),
    ]
    observation = build_observation(signals, "agent_execution_ended", "production")

    assert len(observation.events) == 2
    assert observation.events[0].header.event_type == "tool_execution"
    assert observation.events[0].to_dict() == {
        "header": {"event_type": "tool_execution", "timestamp": "2026-01-01T00:00:00Z"},
        "payload": {"k": "v"},
    }
    assert observation.context.framework == "generic"
    assert observation.context.execution_start_time == "2026-01-01T00:00:00Z"
    assert observation.context.execution_finish_time == "2026-01-01T00:00:05Z"
    assert observation.metadata.spec_version == "1.0"
    assert observation.metadata.event_count == 2
    assert observation.metadata.started_at == "2026-01-01T00:00:00Z"
    assert observation.metadata.completed_at == "2026-01-01T00:00:05Z"
    assert observation.metadata.completion_reason == "agent_execution_ended"
    assert observation.metadata.environment == "production"


def test_build_observation_rejects_empty_list():
    with pytest.raises(ValueError):
        build_observation([], "agent_execution_ended", "production")
