"""ases — the ASES Integration Layer's public package root.

Public Surface (contracts/public-api-contract.md, section 1 — "the ENTIRE
customer-facing API"):

    from ases import ASES
    from ases import configure
    from ases import monitor
    from ases import shutdown

`ASES` is the real engine: construct it, `attach()` one or more Adapters,
`start()`, `stop()`. `monitor()`/`configure()`/`shutdown()` are thin
convenience wrappers over it for the common single-Adapter case
(04-public-api.md section 6 remains the full-control path; see
12-packaging-and-distribution.md's Five-Minute Guide for the fast path these
wrappers exist to serve) — this is the reconciliation Phase 6 of the
Resolution & Integration Plan settled between the two previously-competing
documented shapes, not a second, competing implementation.

Adapter exports — only Adapters actually implemented in this build are
exported here (03-agent-integration-models.md, section 9: "The customer
chooses an Adapter, never a Callback or a Decorator by name.").

GenericAdapter has zero external dependencies and is always importable.
CrewAIAdapter requires the optional `crewai` package (`pip install
ases[crewai]`) — imported lazily here (PEP 562 module __getattr__) so that
merely `import ases`, or `from ases.adapters import GenericAdapter`, never
requires crewai to be installed. This preserves the Core's zero-dependency
promise (ADR-007, 12-packaging-and-distribution.md section 13's Dependency
Policy) even though the CrewAI Adapter now ships inside this same package.
"""

from __future__ import annotations

from typing import List, Optional

from ases.adapters.base import Adapter
from ases.observation.builder import build_observation
from ases.observation.collector import ObservationCollector
from ases.observation.validator import validate_observation
from ases.pipeline.dispatcher import Dispatcher
from ases.pipeline.events import EventSignal
from ases.shared.constants import TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS
from ases.shared.exceptions import ValidationError
from ases.shared.logger import get_logger
from ases.config.settings import Settings, configure_global
from ases.transport.client import APIClient
from ases.transport.queue import ObservationQueue
from ases.transport.worker import Worker

__all__ = [
    "ASES",
    "configure",
    "monitor",
    "shutdown",
    "CrewAIAdapter",
    "GenericAdapter",
    "Execution",
]

_logger = get_logger("ases")

# Backing singleton for the monitor()/shutdown() convenience wrappers
# (public-api-contract.md, section 1). Deliberately module-level, scoped
# only to these two functions' own bookkeeping — never read or written by
# the ASES class itself, which has no notion of being "the default
# instance" (Settings, by contrast, is a genuine process-wide Service; this
# is not — see settings.py's own module docstring for that distinction).
_default_instance: Optional["ASES"] = None


class ASES:
    """The SDK engine (contracts/public-api-contract.md, section 2).

    Owns exactly one instance each of the Observation Collector, the Event
    Pipeline Dispatcher, the Transport Queue, the Transport Worker, and the
    API Client — the full "Golden Chain" a single customer process needs.
    Multiple ASES instances may coexist in the same process (each with its
    own independent chain); this is never required for the common case, but
    nothing here prevents it.
    """

    def __init__(self, api_key: Optional[str] = None) -> None:
        self._settings: Settings = Settings.resolve_for_instance(api_key)
        self._adapters: List[Adapter] = []
        self._started = False

        self._collector = ObservationCollector(on_complete=self._on_observation_ready)
        self._dispatcher = Dispatcher(self._collector)
        self._queue = ObservationQueue()
        self._client = APIClient(self._settings)
        self._worker = Worker(self._queue, self._client)

    def attach(self, adapter: Adapter) -> None:
        """Register an Adapter instance with this SDK. May be called
        multiple times to register multiple Adapters. Must be called before
        start()."""
        adapter._bind(self._dispatcher.dispatch)
        self._adapters.append(adapter)

    def start(self) -> None:
        """Begin accepting Events from all attached Adapters. Explicit and
        idempotent — calling it while already started is a no-op."""
        if self._started:
            return
        self._worker.start()
        for adapter in self._adapters:
            adapter.start_listening()
        self._started = True

    def stop(self) -> None:
        """Stop accepting new Events and flush: every still-open
        Observation is finalized with reason 'sdk_shutdown'
        (09-observation-lifecycle.md, terminating condition 4), then the
        Transport Queue is drained before the Worker thread stops."""
        if not self._started:
            return
        for adapter in self._adapters:
            adapter.stop_listening()
        self._collector.shutdown()
        self._queue.join(timeout=TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS)
        self._worker.stop()
        self._worker.join(timeout=TRANSPORT_SHUTDOWN_FLUSH_TIMEOUT_SECONDS)
        self._started = False

    def _on_observation_ready(self, correlation_id: str, events: List[EventSignal], reason: str) -> None:
        """The Collector's completion sink: build, validate, and enqueue.
        A Validation failure is logged and the Observation is dropped —
        Transport never sees it (09-observation-lifecycle.md, section 8)."""
        observation = build_observation(
            events, reason, self._settings.environment, correlation_id=correlation_id
        )
        try:
            validate_observation(observation)
        except ValidationError as exc:
            _logger.warning(
                "Observation dropped: failed validation (correlation_id=%s): %s.",
                correlation_id, exc,
            )
            return
        self._queue.put(observation)


def configure(api_key: Optional[str] = None) -> None:
    """Module-level configuration (contracts/environment-configuration.md,
    section 4). May be called at most once per process, before any
    ASES(...) or monitor(...) call — a second call raises ConfigurationError
    rather than silently overwriting the first."""
    configure_global(api_key=api_key)


def monitor(adapter: Adapter, api_key: Optional[str] = None) -> "ASES":
    """The fast path (12-packaging-and-distribution.md's Five-Minute Guide):
    construct an ASES instance, attach exactly one Adapter, start it, and
    return the instance — genuinely three lines for the single-Adapter case.

    Uses api_key if given, otherwise falls back to whatever configure()/the
    ASES_API_KEY environment variable already provides, per the same
    precedence rules ASES(...) itself uses (environment-configuration.md,
    section 3) — this function invents no new precedence of its own.

    A customer needing more than one Adapter on a single SDK instance uses
    the ASES class directly instead — monitor() deliberately does not grow
    multi-Adapter support (04-public-api.md's "Public API expresses Intent,
    not Implementation").
    """
    global _default_instance
    instance = ASES(api_key=api_key)
    instance.attach(adapter)
    instance.start()
    _default_instance = instance
    return instance


def shutdown() -> None:
    """Stops the instance most recently created by monitor(). A harmless
    no-op if monitor() was never called — mirrors start()'s own documented
    idempotency (contracts/public-api-contract.md, section 2)."""
    global _default_instance
    if _default_instance is None:
        return
    _default_instance.stop()
    _default_instance = None


def __getattr__(name: str):
    if name == "CrewAIAdapter":
        try:
            from ases.adapters.crewai.adapter import CrewAIAdapter
        except ImportError as exc:
            raise ImportError(
                "CrewAIAdapter requires the optional 'crewai' package. "
                "Install it with: pip install ases[crewai]"
            ) from exc
        return CrewAIAdapter
    if name == "GenericAdapter":
        from ases.adapters.generic.adapter import GenericAdapter

        return GenericAdapter
    if name == "Execution":
        from ases.adapters.generic.adapter import Execution

        return Execution
    raise AttributeError(f"module {__name__!r} has no attribute {name!r}")
