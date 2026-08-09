from ases.observation.collector import ObservationCollector
from ases.pipeline.dispatcher import Dispatcher
from ases.pipeline.events import EventSignal, ObservationCompletedSignal


def test_dispatcher_routes_event_to_collector():
    received = []
    collector = ObservationCollector(on_complete=lambda key, events, reason: received.append((events, reason)))
    dispatcher = Dispatcher(collector)

    ctx = {"execution_id": "abc"}
    dispatcher.dispatch(EventSignal(event_type="tool_execution", payload={}, runtime_context=ctx, timestamp="t1", framework="generic"))
    dispatcher.dispatch(ObservationCompletedSignal(runtime_context=ctx, reason="agent_execution_ended", timestamp="t2"))

    assert len(received) == 1
    events, reason = received[0]
    assert len(events) == 1
    assert reason == "agent_execution_ended"
    collector.shutdown()


def test_dispatcher_ignores_unknown_signal_type():
    collector = ObservationCollector(on_complete=lambda key, events, reason: None)
    dispatcher = Dispatcher(collector)
    # Should not raise — an unrecognized signal is logged and dropped.
    dispatcher.dispatch(object())
    collector.shutdown()
