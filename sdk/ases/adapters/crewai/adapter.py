"""CrewAI Adapter — the Callback Integration Model (Model 1) implementation
for CrewAI, V1's primary framework (01-overview.md, section 7;
07-agent-framework-ecosystem.md).

Verified against the real `crewai` package (tested against crewai==1.15.10)
— not guessed from documentation. CrewAI exposes a process-wide event bus,
`crewai.events.crewai_event_bus`, as its own official Integration Point
(06-integration-point-concept.md, the Callback shape): `register_handler()`
/ `off()` let this Adapter start and stop listening exactly when
`start_listening()` / `stop_listening()` are called — unlike crewai's own
`BaseEventListener` convenience class, which registers at `__init__` time
and would violate the Adapter lifecycle fixed by public-api-contract.md,
section 3.

Every crewai event carries its own `task_id` — this is the Execution
Context the Adapter forwards as `runtime_context`
(09-observation-lifecycle.md, section 5: "The Collector relies on an
Execution Context that the Adapter is able to provide from the framework
itself... CrewAI exposes an Execution object"). It is internal-only and
never appears in the outward-facing ASES JSON.
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any, Dict

from crewai.events import crewai_event_bus
from crewai.events.types.llm_events import LLMCallCompletedEvent, LLMCallStartedEvent
from crewai.events.types.task_events import TaskCompletedEvent, TaskFailedEvent, TaskStartedEvent
from crewai.events.types.tool_usage_events import ToolUsageFinishedEvent, ToolUsageStartedEvent

from ases.adapters.base import Adapter
from ases.pipeline.events import EventSignal, ObservationCompletedSignal
from ases.shared.constants import COMPLETION_REASON_FRAMEWORK_TASK_FINISHED


def _now_iso() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def _runtime_context(task_id: Any) -> Dict[str, Any]:
    return {"crewai_task_id": str(task_id)}


class CrewAIAdapter(Adapter):
    """
    Usage — matches public-api-contract.md, section 7 verbatim:

        from crewai import Crew
        from ases import ASES
        from ases.adapters import CrewAIAdapter

        crew = Crew(...)

        ases = ASES(api_key="ases_xxxxxxxxx")
        adapter = CrewAIAdapter(crew)
        ases.attach(adapter)
        ases.start()

        crew.kickoff()

        ases.stop()

    The `crew` argument is accepted for exact shape-fidelity with the
    documented example. This V1 implementation listens on crewai's
    process-wide event bus (its own documented Integration Point), which is
    correct for the standard case of one Crew per ASES-instrumented
    process; the `crew` reference is kept for a future per-Crew scope if
    crewai's own event bus adds one.
    """

    FRAMEWORK_NAME = "crewai"

    def __init__(self, crew: Any = None) -> None:
        super().__init__()
        self._crew = crew
        # One dict, registered/unregistered as a unit in start_listening()/
        # stop_listening() — this IS the Adapter's entire intelligence: a
        # fixed translation table, nothing decided dynamically per Event.
        self._handlers = {
            TaskStartedEvent: self._on_task_started,
            ToolUsageStartedEvent: self._on_tool_usage_started,
            ToolUsageFinishedEvent: self._on_tool_usage_finished,
            LLMCallStartedEvent: self._on_llm_call_started,
            LLMCallCompletedEvent: self._on_llm_call_completed,
            TaskCompletedEvent: self._on_task_completed,
            TaskFailedEvent: self._on_task_failed,
        }

    def start_listening(self) -> None:
        for event_type, handler in self._handlers.items():
            crewai_event_bus.register_handler(event_type, handler)

    def stop_listening(self) -> None:
        for event_type, handler in self._handlers.items():
            crewai_event_bus.off(event_type, handler)

    # --- Handlers -----------------------------------------------------
    # Each one translates a single crewai-specific event into the
    # Canonical Event Model and forwards it — never interpreting,
    # aggregating, or deciding anything beyond that (ADR-002).

    # Mapping from crewai's own callback vocabulary to the Backend's ten
    # canonical Event Dictionary values (RC-7, PIPELINE-001/WALK-008, Ground
    # 2). No dedicated category exists for a Task lifecycle marker or an LLM
    # call in the Backend's Event Dictionary, so both map to "custom" — the
    # Dictionary's own explicit catch-all for "framework-specific events."
    # The original crewai event name is preserved in the payload (never
    # validated beyond "is an object" by the Backend, per
    # ADR-002-structural-validation-only.md) so it isn't lost, only
    # relocated out of the validated `header.event_type` field. Documented
    # in full in 07-agent-framework-ecosystem.md.
    _EVENT_TYPE_MAP = {
        "task_started": "custom",
        "tool_usage_started": "tool_execution",
        "tool_usage_finished": "tool_execution",
        "llm_call_started": "custom",
        "llm_call_completed": "custom",
    }

    def _on_task_started(self, source: Any, event: TaskStartedEvent) -> None:
        self._emit_event(
            "task_started",
            {"task_name": event.task_name, "agent_role": event.agent_role},
            event.task_id,
        )

    def _on_tool_usage_started(self, source: Any, event: ToolUsageStartedEvent) -> None:
        self._emit_event(
            "tool_usage_started",
            {"tool": event.tool_name, "args": event.tool_args},
            event.task_id,
        )

    def _on_tool_usage_finished(self, source: Any, event: ToolUsageFinishedEvent) -> None:
        self._emit_event(
            "tool_usage_finished",
            {"tool": event.tool_name, "from_cache": event.from_cache},
            event.task_id,
        )

    def _on_llm_call_started(self, source: Any, event: LLMCallStartedEvent) -> None:
        self._emit_event("llm_call_started", {"model": event.model}, event.task_id)

    def _on_llm_call_completed(self, source: Any, event: LLMCallCompletedEvent) -> None:
        self._emit_event(
            "llm_call_completed",
            {"model": event.model, "finish_reason": event.finish_reason},
            event.task_id,
        )

    def _on_task_completed(self, source: Any, event: TaskCompletedEvent) -> None:
        # Terminating condition 1 from 09-observation-lifecycle.md, section 3:
        # "The Framework explicitly reports 'Task Finished.' -> send
        # immediately. (Primary, easiest case.)"
        self._complete(event.task_id, COMPLETION_REASON_FRAMEWORK_TASK_FINISHED)

    def _on_task_failed(self, source: Any, event: TaskFailedEvent) -> None:
        # A failed Task is still a finished Task — the Observation still
        # belongs in SentinelX; nothing in the Lifecycle document treats
        # failure as a non-terminating condition.
        self._complete(event.task_id, COMPLETION_REASON_FRAMEWORK_TASK_FINISHED)

    def _emit_event(self, crewai_event_name: str, payload: Dict[str, Any], task_id: Any) -> None:
        canonical_event_type = self._EVENT_TYPE_MAP[crewai_event_name]
        signal = EventSignal(
            event_type=canonical_event_type,
            payload={**payload, "crewai_event": crewai_event_name},
            runtime_context=_runtime_context(task_id),
            timestamp=_now_iso(),
            framework=self.FRAMEWORK_NAME,
        )
        self._forward(signal)

    def _complete(self, task_id: Any, reason: str) -> None:
        signal = ObservationCompletedSignal(
            runtime_context=_runtime_context(task_id),
            reason=reason,
            timestamp=_now_iso(),
        )
        self._forward(signal)
