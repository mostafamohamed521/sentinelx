"""Demonstrates the full ASES + CrewAI pipeline with ZERO external
services -- no LLM API key, no network call to SentinelX either (Transport
is mocked at the last possible point, exactly like the SDK's own test
suite). Every event in between is a genuine `crewai` Pydantic event object,
dispatched through crewai's own real event bus, and translated by the real
CrewAIAdapter -- only the actual `crew.kickoff()` LLM call and the final
SentinelX HTTP POST are stood in for.

This mirrors 07-agent-framework-ecosystem.md, section 4: "using local model
runtimes... Sentinel's whole pipeline can be demonstrated end to end with
zero external services."

Note: this demo emits Task and LLM-call events only. crewai's Tool-usage
events are routed through an additional internal async rendering path (for
its own console output) that can reorder relative to a synchronous script
like this one -- that reordering is a crewai console-output detail, not an
ASES correctness issue (see tests/adapters/test_crewai_adapter.py, which
verifies tool-usage event translation directly and deterministically).
"""

import time
from unittest.mock import patch

from crewai.events import crewai_event_bus
from crewai.events.types.llm_events import LLMCallCompletedEvent, LLMCallStartedEvent, LLMCallType
from crewai.events.types.task_events import TaskCompletedEvent, TaskStartedEvent
from crewai.tasks.task_output import TaskOutput

from ases import ASES
from ases.adapters import CrewAIAdapter

captured_payloads = []


def _fake_send(self, serialized_observation: str) -> bool:
    captured_payloads.append(serialized_observation)
    return True


with patch("ases.transport.client.APIClient.send", _fake_send):
    ases = ASES(api_key="ases_demo_key")
    adapter = CrewAIAdapter()
    ases.attach(adapter)
    ases.start()

    # --- Simulating what crew.kickoff() emits for one Task --------------
    task_id = "demo-task-1"

    crewai_event_bus.emit(
        source=None,
        event=TaskStartedEvent(context="Summarize the latest AI Agent security incident", task_id=task_id),
    )
    crewai_event_bus.emit(
        source=None, event=LLMCallStartedEvent(call_id="call-1", model="gpt-4o", task_id=task_id)
    )
    crewai_event_bus.emit(
        source=None,
        event=LLMCallCompletedEvent(
            call_id="call-1",
            model="gpt-4o",
            task_id=task_id,
            response="The incident involved a prompt-injection attack against a customer support agent.",
            call_type=LLMCallType.LLM_CALL,
        ),
    )
    output = TaskOutput(description="Summarized the incident in three sentences.", agent="Security Researcher")
    crewai_event_bus.emit(source=None, event=TaskCompletedEvent(output=output, task_id=task_id))
    # ------------------------------------------------------------------------

    time.sleep(1.5)  # let the background Worker drain the Transport Queue
    ases.stop()

print(f"ASES delivered {len(captured_payloads)} Observation(s) to (mocked) SentinelX:\n")
for payload in captured_payloads:
    print(payload)
