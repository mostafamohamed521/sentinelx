"""Transport package public surface — assembles Queue, Worker, and API
Client into the single orchestrator the rest of the SDK talks to
(10-transport-layer.md, section 7: "Transport itself is not a monolith —
it decomposes into four focused pieces.").
"""

from __future__ import annotations

from ases.config.settings import Settings
from ases.observation.models import Observation
from ases.shared.constants import TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS
from ases.shared.logger import get_logger
from ases.transport.client import APIClient
from ases.transport.queue import ObservationQueue
from ases.transport.worker import Worker


class Transport:
    def __init__(self, settings: Settings) -> None:
        self._queue = ObservationQueue()
        self._client = APIClient(settings)
        self._worker = Worker(self._queue, self._client)
        self._logger = get_logger("transport")
        self._started = False

    def start(self) -> None:
        if self._started:
            return
        self._worker.start()
        self._started = True

    def enqueue(self, observation: Observation) -> None:
        self._queue.put(observation)

    def stop(self) -> None:
        """Best-effort Flush with a short timeout, then stop regardless
        (10-transport-layer.md, section 5)."""
        if not self._started:
            return
        drained = self._queue.join(timeout=TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS)
        if not drained:
            self._logger.warning(
                "Shutdown flush timed out after %ss with %d Observation(s) "
                "still queued — dropping.",
                TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS, self._queue.qsize(),
            )
        self._worker.stop()
        self._started = False


__all__ = ["Transport"]
