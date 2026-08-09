"""Transport Queue — an in-memory FIFO queue holding Observations awaiting
delivery (10-transport-layer.md, section 4). Not RabbitMQ, not Kafka: the
SDK lives inside a single process, so Python's own thread-safe queue.Queue
is sufficient and avoids the unjustified operational weight a distributed
broker would add (ADR-004). Not persisted to disk in V1 (ADR-003).
"""

from __future__ import annotations

import queue
import time
from typing import Optional

from ases.observation.models import Observation


class ObservationQueue:
    def __init__(self) -> None:
        self._queue: "queue.Queue[Observation]" = queue.Queue()

    def put(self, observation: Observation) -> None:
        self._queue.put(observation)

    def get(self, timeout: float = 1.0) -> Optional[Observation]:
        try:
            return self._queue.get(timeout=timeout)
        except queue.Empty:
            return None

    def task_done(self) -> None:
        self._queue.task_done()

    def qsize(self) -> int:
        return self._queue.qsize()

    def join(self, timeout: Optional[float] = None) -> bool:
        """Block until every enqueued Observation has been task_done()'d, or
        timeout elapses. Returns True if fully drained in time — used by
        Transport's shutdown Flush (10-transport-layer.md, section 5).

        queue.Queue.join() has no built-in timeout, so a short polling loop
        approximates one here.
        """
        if timeout is None:
            self._queue.join()
            return True
        deadline = time.monotonic() + timeout
        while self._queue.unfinished_tasks > 0:
            if time.monotonic() >= deadline:
                return False
            time.sleep(0.05)
        return True
