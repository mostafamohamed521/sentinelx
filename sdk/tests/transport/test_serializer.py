import json

from ases.observation.models import Context, Event, EventHeader, Observation, ObservationMetadata
from ases.transport.serializer import serialize_observation


def test_serialize_observation_produces_valid_json():
    context = Context(
        framework="generic",
        environment="production",
        execution_start_time="t1",
        execution_finish_time="t2",
    )
    metadata = ObservationMetadata(
        spec_version="1.0",
        sdk_version="1.0.0",
        environment="production",
        started_at="t1",
        completed_at="t2",
        completion_reason="agent_execution_ended",
        event_count=1,
    )
    observation = Observation(
        context=context,
        events=[
            Event(
                header=EventHeader(event_type="tool_execution", timestamp="t1"),
                payload={"tool": "search"},
            )
        ],
        metadata=metadata,
    )
    serialized = serialize_observation(observation)
    parsed = json.loads(serialized)

    assert parsed["context"]["framework"] == "generic"
    assert parsed["events"][0]["header"]["event_type"] == "tool_execution"
    assert parsed["events"][0]["payload"] == {"tool": "search"}
    assert parsed["metadata"]["spec_version"] == "1.0"
    assert parsed["metadata"]["event_count"] == 1
    assert parsed["metadata"]["completion_reason"] == "agent_execution_ended"
