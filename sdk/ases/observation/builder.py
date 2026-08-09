"""Observation Builder — the first component in the pipeline that knows the
ASES Schema (08-internal-architecture.md, section 4). Framework-independent
and Stateless: it runs exactly once, after the Collector finishes
gathering Events, never incrementally per-Event (09-observation-lifecycle.md,
section 7: "The Builder is Stateless. It runs once, after collection
completes, and only the Collector ever tells it to start.").
"""

from __future__ import annotations

from typing import List

from ases.observation.models import Context, Event, EventHeader, Observation, ObservationMetadata
from ases.pipeline.events import EventSignal
from ases.shared.constants import SDK_VERSION, SPEC_VERSION


def build_observation(
    signals: List[EventSignal],
    completion_reason: str,
    environment: str,
    correlation_id: str = "",
) -> Observation:
    """Convert a finished group of EventSignals into a single Observation,
    in the Backend's real wire-format shape — Context + Events (each
    header/payload-nested) + Metadata (RC-7 / Ground 1).

    `correlation_id` (RC-9, RELIABILITY-005) is the Collector's own
    internal grouping key for this execution — carried on the returned
    Observation for diagnostic logging only; Observation.to_dict() never
    includes it.

    Raises ValueError if given an empty list — the Collector never calls
    this with zero Events (see collector._finalize's own empty-events
    guard), so an empty list here indicates a caller error, not a normal
    runtime condition.
    """
    if not signals:
        raise ValueError("build_observation() requires at least one EventSignal.")

    events = [
        Event(
            header=EventHeader(event_type=s.event_type, timestamp=s.timestamp),
            payload=s.payload,
        )
        for s in signals
    ]

    context = Context(
        framework=signals[0].framework,
        environment=environment,
        execution_start_time=signals[0].timestamp,
        execution_finish_time=signals[-1].timestamp,
    )

    metadata = ObservationMetadata(
        spec_version=SPEC_VERSION,
        sdk_version=SDK_VERSION,
        environment=environment,
        started_at=signals[0].timestamp,
        completed_at=signals[-1].timestamp,
        completion_reason=completion_reason,
        event_count=len(events),
    )
    return Observation(context=context, events=events, metadata=metadata, correlation_id=correlation_id)
