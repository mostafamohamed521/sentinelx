from ases.observation.models import Context, Observation, ObservationMetadata
from ases.transport.queue import ObservationQueue


def _dummy_observation():
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
    return Observation(context=context, events=[], metadata=metadata)


def test_queue_put_get_roundtrip():
    q = ObservationQueue()
    obs = _dummy_observation()
    q.put(obs)
    result = q.get(timeout=1.0)
    assert result is obs
    q.task_done()


def test_queue_get_returns_none_on_empty():
    q = ObservationQueue()
    assert q.get(timeout=0.1) is None


def test_queue_join_returns_true_once_drained():
    q = ObservationQueue()
    q.put(_dummy_observation())
    q.get(timeout=1.0)
    q.task_done()
    assert q.join(timeout=0.5) is True


def test_queue_join_times_out_when_not_drained():
    q = ObservationQueue()
    q.put(_dummy_observation())
    drained = q.join(timeout=0.2)
    assert drained is False
    q.get(timeout=1.0)
    q.task_done()  # cleanup so the queue isn't left dangling
