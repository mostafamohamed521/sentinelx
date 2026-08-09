import time

from ases.observation import collector as collector_module
from ases.observation.collector import ObservationCollector
from ases.pipeline.events import EventSignal, ObservationCompletedSignal


def test_collector_groups_events_and_completes():
    completed = []

    def sink(key, events, reason):
        completed.append((events, reason))

    collector = ObservationCollector(on_complete=sink)
    ctx = {"execution_id": "abc"}
    collector.on_event(EventSignal(event_type="tool_execution", payload={}, runtime_context=ctx, timestamp="t1", framework="generic"))
    collector.on_event(EventSignal(event_type="custom", payload={}, runtime_context=ctx, timestamp="t2", framework="generic"))
    collector.on_observation_completed(
        ObservationCompletedSignal(runtime_context=ctx, reason="agent_execution_ended", timestamp="t3")
    )

    assert len(completed) == 1
    events, reason = completed[0]
    assert len(events) == 2
    assert reason == "agent_execution_ended"
    collector.shutdown()


def test_collector_handles_concurrent_observations_independently():
    completed = []

    def sink(key, events, reason):
        completed.append(events)

    collector = ObservationCollector(on_complete=sink)
    ctx_a = {"execution_id": "a"}
    ctx_b = {"execution_id": "b"}
    collector.on_event(EventSignal(event_type="tool_execution", payload={}, runtime_context=ctx_a, timestamp="t1", framework="generic"))
    collector.on_event(EventSignal(event_type="tool_execution", payload={}, runtime_context=ctx_b, timestamp="t1", framework="generic"))
    collector.on_observation_completed(
        ObservationCompletedSignal(runtime_context=ctx_a, reason="agent_execution_ended", timestamp="t2")
    )
    collector.on_observation_completed(
        ObservationCompletedSignal(runtime_context=ctx_b, reason="agent_execution_ended", timestamp="t2")
    )

    assert len(completed) == 2
    collector.shutdown()


def test_collector_shutdown_flushes_open_observations():
    completed = []

    def sink(key, events, reason):
        completed.append(reason)

    collector = ObservationCollector(on_complete=sink)
    collector.on_event(
        EventSignal(event_type="tool_execution", payload={}, runtime_context={"execution_id": "x"}, timestamp="t1", framework="generic")
    )
    collector.shutdown()

    assert completed == ["sdk_shutdown"]


def test_collector_force_closes_on_timeout(monkeypatch):
    monkeypatch.setattr(collector_module.constants, "OBSERVATION_TIMEOUT_SECONDS", 0.2)

    completed = []

    def sink(key, events, reason):
        completed.append(reason)

    collector = ObservationCollector(on_complete=sink)
    collector.on_event(
        EventSignal(event_type="tool_execution", payload={}, runtime_context={"execution_id": "y"}, timestamp="t1", framework="generic")
    )

    time.sleep(1.5)  # the timeout loop polls once a second
    assert completed == ["timeout"]
    collector.shutdown()


def test_completed_signal_with_no_matching_events_is_ignored():
    completed = []
    collector = ObservationCollector(on_complete=lambda key, events, reason: completed.append(reason))
    collector.on_observation_completed(
        ObservationCompletedSignal(runtime_context={"execution_id": "never-seen"}, reason="agent_execution_ended", timestamp="t1")
    )
    assert completed == []
    collector.shutdown()
