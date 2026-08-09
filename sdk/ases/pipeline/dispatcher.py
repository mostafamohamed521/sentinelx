"""Event Pipeline — the framework-agnostic entry point every Adapter's
output funnels through (08-internal-architecture.md, section 3).

The Dispatcher does the least possible amount of work: receive a signal,
confirm it's one of the two known types, forward it to the Observation
Collector. It is an Orchestrator — specifically not a Builder, not a
Serializer, not a Transport mechanism.
"""

from __future__ import annotations

from typing import Union

from ases.observation.collector import ObservationCollector
from ases.pipeline.events import EventSignal, ObservationCompletedSignal
from ases.shared.logger import get_logger

Signal = Union[EventSignal, ObservationCompletedSignal]


class Dispatcher:
    def __init__(self, collector: ObservationCollector) -> None:
        self._collector = collector
        self._logger = get_logger("pipeline.dispatcher")

    def dispatch(self, signal: Signal) -> None:
        if isinstance(signal, EventSignal):
            self._collector.on_event(signal)
        elif isinstance(signal, ObservationCompletedSignal):
            self._collector.on_observation_completed(signal)
        else:
            # Defensive only: the Adapter base class and every shipped
            # Adapter can only ever construct these two signal types
            # (ADR-002). Reaching this branch means a third-party Adapter
            # violated the contract.
            self._logger.warning(
                "Dispatcher received an unrecognized signal type (%r) — dropped.",
                type(signal),
            )
