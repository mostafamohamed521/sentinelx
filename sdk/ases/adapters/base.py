"""The base Adapter contract every framework Adapter — shipped or
third-party — must satisfy (04-public-api.md, section 7;
public-api-contract.md, section 3).

An Adapter must do the least possible amount of work (ADR-002): it never
builds an Observation, never performs an HTTP request, never implements
retry logic, and never imports the Transport layer. Its only job is to
notice that something happened and say so.
"""

from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Callable, Optional, Union

from ases.pipeline.events import EventSignal, ObservationCompletedSignal

Signal = Union[EventSignal, ObservationCompletedSignal]


class Adapter(ABC):
    #: Identifies which framework this Adapter represents, for the
    #: Observation's wire-format `context.framework` field (RC-7, Ground 1).
    #: Every concrete Adapter overrides this; "custom" is a safe default for
    #: third-party Adapters that don't.
    FRAMEWORK_NAME: str = "custom"

    def __init__(self) -> None:
        self._emit_signal: Optional[Callable[[Signal], None]] = None

    def _bind(self, emit_signal: Callable[[Signal], None]) -> None:
        """Called internally by ASES.attach() to wire this Adapter to the
        SDK's Dispatcher. Never called directly by customer code."""
        self._emit_signal = emit_signal

    def _forward(self, signal: Signal) -> None:
        if self._emit_signal is None:
            raise RuntimeError(
                "This Adapter has not been attached to an ASES instance "
                "yet — call ases.attach(adapter) before emitting any signal."
            )
        self._emit_signal(signal)

    @abstractmethod
    def start_listening(self) -> None:
        """Begin listening for framework events. Called internally when
        ases.start() runs."""

    @abstractmethod
    def stop_listening(self) -> None:
        """Stop listening for framework events. Called internally when
        ases.stop() runs."""
