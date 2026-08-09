"""Observation Collector — groups related Events, owns the Observation
Lifecycle state machine, and decides when an Observation is complete
(08-internal-architecture.md, section 9; 09-observation-lifecycle.md).

Storage is in-memory only, per ADR-003: no database, file, or external cache
holds in-flight Events. If the host process crashes, whatever is still
in-flight here is lost — an accepted, documented V1 tradeoff, because an
Observation's entire lifespan is measured in seconds and a process crash
already means the Agent itself has crashed.

Four terminating conditions are implemented, in the priority order fixed by
09-observation-lifecycle.md, section 3:
    1. framework_task_finished / agent_execution_ended — an explicit
       OBSERVATION_COMPLETED signal from the Adapter.
    2. timeout — no new Event for OBSERVATION_TIMEOUT_SECONDS.
    3. sdk_shutdown — flushed when ases.stop() is called.
"""

from __future__ import annotations

import json
import threading
import time
from dataclasses import dataclass, field
from typing import Any, Callable, Dict, List

from ases.pipeline.events import EventSignal, ObservationCompletedSignal
from ases.shared import constants
from ases.shared.constants import COMPLETION_REASON_SDK_SHUTDOWN, COMPLETION_REASON_TIMEOUT
from ases.shared.logger import get_logger

# The callback the Collector hands finished Observations to. A plain
# function, not a class — this keeps the Collector decoupled from the
# Builder: "Collector -> Builder, direction only ever flows this way"
# (08-internal-architecture.md, section 7). The Collector never imports the
# Builder itself.
#
# The correlation key (str) is included so a permanently-dropped
# Observation's log entry (RC-9, RELIABILITY-005) can reference which
# in-flight execution it was, without that identifier ever reaching the
# wire-format JSON — it is carried as Observation.correlation_id, which
# Observation.to_dict() deliberately excludes.
CompletionSink = Callable[[str, List[EventSignal], str], None]


def _correlation_key(runtime_context: Dict[str, Any]) -> str:
    """Turn an Adapter-supplied runtime_context into a stable, comparable
    key. runtime_context is intentionally opaque and Adapter-specific
    (adapter-signal-contract.md, section 4) — the Collector only needs a
    stable value per execution, never to understand its internal structure.
    A sorted-key JSON dump satisfies that without requiring
    runtime_context's values to be hashable themselves."""
    return json.dumps(runtime_context, sort_keys=True, default=str)


@dataclass
class _InFlightObservation:
    events: List[EventSignal] = field(default_factory=list)
    last_event_at: float = field(default_factory=time.monotonic)


class ObservationCollector:
    def __init__(self, on_complete: CompletionSink) -> None:
        self._on_complete = on_complete
        self._logger = get_logger("observation.collector")
        self._lock = threading.Lock()
        self._in_flight: Dict[str, _InFlightObservation] = {}
        self._stop_event = threading.Event()
        self._timeout_thread = threading.Thread(
            target=self._timeout_loop, name="ases-collector-timeout", daemon=True
        )
        self._timeout_thread.start()

    def on_event(self, signal: EventSignal) -> None:
        """The Collector begins an Observation at the first Event it
        receives (09-observation-lifecycle.md, section 2)."""
        key = _correlation_key(signal.runtime_context)
        with self._lock:
            in_flight = self._in_flight.get(key)
            if in_flight is None:
                in_flight = _InFlightObservation()
                self._in_flight[key] = in_flight
                self._logger.debug("Observation started (key=%s).", key)
            in_flight.events.append(signal)
            in_flight.last_event_at = time.monotonic()

    def on_observation_completed(self, signal: ObservationCompletedSignal) -> None:
        key = _correlation_key(signal.runtime_context)
        self._finalize(key, signal.reason)

    def shutdown(self) -> None:
        """Flush every still-open Observation with reason 'sdk_shutdown'
        (09-observation-lifecycle.md, terminating condition 4)."""
        self._stop_event.set()
        with self._lock:
            keys = list(self._in_flight.keys())
        for key in keys:
            self._finalize(key, COMPLETION_REASON_SDK_SHUTDOWN)

    def _finalize(self, key: str, reason: str) -> None:
        with self._lock:
            in_flight = self._in_flight.pop(key, None)
        if in_flight is None or not in_flight.events:
            # An OBSERVATION_COMPLETED signal with no matching Events (or
            # one that arrives after a timeout already closed this key) is
            # not an error — it can happen legitimately when a completion
            # signal and a timeout race each other.
            return
        self._logger.debug(
            "Observation completed (key=%s, reason=%s, events=%d).",
            key, reason, len(in_flight.events),
        )
        self._on_complete(key, in_flight.events, reason)

    def _timeout_loop(self) -> None:
        # Polls once a second rather than scheduling a per-Observation
        # timer — simpler, and accurate enough for a 30-second threshold.
        while not self._stop_event.wait(timeout=1.0):
            now = time.monotonic()
            with self._lock:
                expired_keys = [
                    key
                    for key, obs in self._in_flight.items()
                    if now - obs.last_event_at >= constants.OBSERVATION_TIMEOUT_SECONDS
                ]
            for key in expired_keys:
                self._finalize(key, COMPLETION_REASON_TIMEOUT)
