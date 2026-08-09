import pytest

from ases.pipeline.events import EventSignal, ObservationCompletedSignal
from ases.shared.exceptions import AdapterError


def test_event_signal_requires_event_type():
    with pytest.raises(AdapterError):
        EventSignal(event_type="", payload={}, runtime_context={"x": 1}, timestamp="2026-01-01T00:00:00Z", framework="generic")


def test_event_signal_requires_dict_payload():
    with pytest.raises(AdapterError):
        EventSignal(event_type="tool_execution", payload="not-a-dict", runtime_context={"x": 1}, timestamp="t", framework="generic")


def test_event_signal_requires_runtime_context():
    with pytest.raises(AdapterError):
        EventSignal(event_type="tool_execution", payload={}, runtime_context={}, timestamp="t", framework="generic")


def test_event_signal_has_fixed_signal_type():
    signal = EventSignal(event_type="tool_execution", payload={"a": 1}, runtime_context={"x": 1}, timestamp="t", framework="generic")
    assert signal.signal_type == "EVENT"


def test_observation_completed_signal_validates_reason():
    with pytest.raises(AdapterError):
        ObservationCompletedSignal(runtime_context={"x": 1}, reason="not_a_real_reason", timestamp="t")


def test_observation_completed_signal_accepts_valid_reason():
    signal = ObservationCompletedSignal(runtime_context={"x": 1}, reason="agent_execution_ended", timestamp="t")
    assert signal.signal_type == "OBSERVATION_COMPLETED"
