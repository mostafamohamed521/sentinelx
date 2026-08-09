"""GenericAdapter — the Manual API fallback for Agents with no
framework-provided Integration Point (03-agent-integration-models.md,
Model 4; public-api-contract.md, section 4).

This is the Adapter a custom Python application — including a Django-based
AI Agent — uses directly. The customer calls emit() at the points in their
own code where a Tool runs, an LLM call happens, a file is accessed, or any
other meaningful occurrence takes place. Everything downstream — grouping
into an Observation, building ASES JSON, delivering it — is handled
automatically by the Core, exactly as it is for a framework Adapter
(05-customer-integration-journey.md, section 8: "No framework, but real
Python code: this is where the Generic Adapter and the Manual API apply —
a normal, supported scenario.").
"""

from __future__ import annotations

import uuid
from datetime import datetime, timezone
from typing import Any, Dict, Optional

from ases.adapters.base import Adapter
from ases.pipeline.events import EventSignal, ObservationCompletedSignal
from ases.shared.constants import COMPLETION_REASON_AGENT_EXECUTION_ENDED
from ases.shared.exceptions import AdapterError


def _now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


class Execution:
    """A single correlated in-flight Task, identified by an internal-only
    execution_id carried as runtime_context — never sent in the
    outward-facing ASES JSON (adapter-signal-contract.md, section 4).

    Use this directly (via GenericAdapter.begin_execution()) whenever your
    Agent may run multiple Tasks concurrently — e.g. concurrent Django
    requests each running their own Agent Task
    (09-observation-lifecycle.md, section 4: "Can Multiple Observations
    Exist at Once? Yes, definitely.").
    """

    def __init__(self, adapter: "GenericAdapter", execution_id: str) -> None:
        self._adapter = adapter
        self._runtime_context = {"execution_id": execution_id}
        self._completed = False

    def emit(self, event_type: str, payload: Dict[str, Any]) -> None:
        if self._completed:
            raise AdapterError(
                "This Execution has already been completed — start a new "
                "one with adapter.begin_execution() for the next Task."
            )
        if not isinstance(payload, dict):
            raise AdapterError("payload must be a dict.")
        # event_type is validated against the canonical Event Dictionary by
        # EventSignal itself (pipeline/events.py) — a non-canonical value
        # raises AdapterError here with a clear, actionable message listing
        # the ten valid values (RC-7, Ground 2). The Manual API's customer
        # is responsible for supplying a canonical value directly; there is
        # no framework-specific Adapter mapping to protect them here.
        signal = EventSignal(
            event_type=event_type,
            payload=payload,
            runtime_context=self._runtime_context,
            timestamp=_now_iso(),
            framework=self._adapter.FRAMEWORK_NAME,
        )
        self._adapter._forward(signal)

    def complete(self, reason: str = COMPLETION_REASON_AGENT_EXECUTION_ENDED) -> None:
        if self._completed:
            return
        signal = ObservationCompletedSignal(
            runtime_context=self._runtime_context,
            reason=reason,
            timestamp=_now_iso(),
        )
        self._adapter._forward(signal)
        self._completed = True


class GenericAdapter(Adapter):
    """
    Concurrent usage (recommended for Django Agents serving multiple
    requests at once)::

        adapter = GenericAdapter()
        ases.attach(adapter)
        ases.start()

        execution = adapter.begin_execution()
        execution.emit("tool_execution", {"tool": "search", "query": "..."})
        execution.emit("custom", {"kind": "llm_call", "model": "gpt-4o", "tokens": 812})
        execution.complete()

    Single-execution shortcut, for simple scripts that never run two Tasks
    at once::

        adapter.emit("tool_execution", {"tool": "search", "query": "..."})
        adapter.complete()

    `event_type` must be one of the Backend's ten canonical Event
    Dictionary values (`api_call`, `file_access`, `command_execution`,
    `network_connection`, `database_operation`, `tool_execution`,
    `memory_operation`, `authentication`, `configuration_change`,
    `custom`) — emit() raises AdapterError immediately, with the full list,
    if given anything else (RC-7, Ground 2).
    """

    FRAMEWORK_NAME = "generic"

    def __init__(self) -> None:
        super().__init__()
        self._default_execution: Optional[Execution] = None

    def start_listening(self) -> None:
        # The Generic Adapter has no framework to attach to — the customer
        # drives it explicitly via emit()/complete(). Present only for
        # Adapter-contract compliance (public-api-contract.md, section 3).
        pass

    def stop_listening(self) -> None:
        pass

    def begin_execution(self) -> Execution:
        """Start a new, independently-correlated execution."""
        return Execution(self, execution_id=str(uuid.uuid4()))

    def emit(self, event_type: str, payload: Dict[str, Any]) -> None:
        """Shortcut for single-execution Agents. Lazily creates a default
        Execution on first use. Do not mix this with begin_execution() on
        the same adapter instance if your Agent needs concurrent Task
        correlation — use begin_execution() exclusively in that case."""
        if self._default_execution is None:
            self._default_execution = self.begin_execution()
        self._default_execution.emit(event_type, payload)

    def complete(self, reason: str = COMPLETION_REASON_AGENT_EXECUTION_ENDED) -> None:
        if self._default_execution is None:
            raise AdapterError("complete() called before any Event was emitted.")
        self._default_execution.complete(reason)
        self._default_execution = None
