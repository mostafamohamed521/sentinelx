"""Worker — a background thread that pulls Observations from the Queue and
hands them to the Serializer and API Client (10-transport-layer.md,
section 7). The Worker never sends anything itself — it delegates entirely
to the API Client, so a future transport mechanism (e.g. a message broker)
only ever touches the Client and/or Serializer, never the Queue or Worker.
"""

from __future__ import annotations

import threading
from typing import Optional

from ases.transport.client import APIClient
from ases.transport.queue import ObservationQueue
from ases.transport.serializer import serialize_observation
from ases.shared.logger import get_logger


class Worker:
    def __init__(self, obs_queue: ObservationQueue, client: APIClient) -> None:
        self._queue = obs_queue
        self._client = client
        self._logger = get_logger("transport.worker")
        self._stop_event = threading.Event()
        self._thread = threading.Thread(
            target=self._run, name="ases-transport-worker", daemon=True
        )

    def start(self) -> None:
        self._thread.start()

    def stop(self) -> None:
        self._stop_event.set()

    def join(self, timeout: Optional[float] = None) -> None:
        self._thread.join(timeout=timeout)

    def _run(self) -> None:
        while not self._stop_event.is_set():
            observation = self._queue.get(timeout=1.0)
            if observation is None:
                continue
            try:
                payload = serialize_observation(observation)
                delivered = self._client.send(payload)
                if not delivered:
                    # RC-9, RELIABILITY-005 — structured content, at
                    # minimum: the Observation's internal correlation_id
                    # (never the wire-format JSON, which has none — see
                    # models.py), and a timestamp (via the standard log
                    # formatter, logger.py). The specific failure reason
                    # (retries exhausted, authentication failure,
                    # non-retryable rejection — RC-8 IDENTITY-002's
                    # three-way classification) is on the API Client's own
                    # immediately-preceding warning, referenced here rather
                    # than duplicated, so the two messages can never drift
                    # out of sync with each other.
                    self._logger.warning(
                        "Observation dropped (correlation_id=%s, event_count=%d) "
                        "— see the preceding warning from transport.client for the failure reason.",
                        observation.correlation_id, len(observation.events),
                    )
            except Exception:  # noqa: BLE001 — Transport must never crash the
                # host Agent (ADR-004); a defect here must be logged and
                # swallowed, not left to kill this daemon thread silently.
                self._logger.exception(
                    "Unexpected error while delivering an Observation — dropped."
                )
            finally:
                self._queue.task_done()
