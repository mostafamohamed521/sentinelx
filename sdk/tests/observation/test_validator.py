from dataclasses import replace

import pytest

from ases.observation.builder import build_observation
from ases.observation.models import Context, Observation, ObservationMetadata
from ases.observation.validator import validate_observation
from ases.pipeline.events import EventSignal
from ases.shared.exceptions import ValidationError


def _valid_observation():
    signals = [
        EventSignal(event_type="tool_execution", payload={"a": 1}, runtime_context={"x": 1}, timestamp="2026-01-01T00:00:00Z", framework="generic")
    ]
    return build_observation(signals, "agent_execution_ended", "production")


def test_valid_observation_passes():
    validate_observation(_valid_observation())  # must not raise


def test_empty_events_raises():
    context = Context(
        framework="generic",
        environment="production",
        execution_start_time="t",
        execution_finish_time="t",
    )
    metadata = ObservationMetadata(
        spec_version="1.0",
        sdk_version="1.0.0",
        environment="production",
        started_at="t",
        completed_at="t",
        completion_reason="agent_execution_ended",
        event_count=0,
    )
    observation = Observation(context=context, events=[], metadata=metadata)
    with pytest.raises(ValidationError):
        validate_observation(observation)


def test_mismatched_event_count_raises():
    observation = _valid_observation()
    bad_metadata = replace(observation.metadata, event_count=999)
    bad_observation = replace(observation, metadata=bad_metadata)
    with pytest.raises(ValidationError):
        validate_observation(bad_observation)


def test_invalid_completion_reason_raises():
    observation = _valid_observation()
    bad_metadata = replace(observation.metadata, completion_reason="not_a_real_reason")
    bad_observation = replace(observation, metadata=bad_metadata)
    with pytest.raises(ValidationError):
        validate_observation(bad_observation)
