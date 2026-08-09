"""Data models for Event, Observation, and ObservationMetadata.

Per 13-implementation-roadmap.md, Phase 4: pure data shapes with no logic
attached, built before the Collector, Builder, or Validator.

!! SCHEMA ASSUMPTION — CONFIRM AGAINST THE REAL BACKEND !!

09-observation-lifecycle.md references an external "ASES JSON Schema
documentation" that defines the exact wire format SentinelX expects. That
document was not part of the documentation set this SDK was built from. The
shape below is a deliberate, documented reconstruction from everything that
*is* specified:

  - The Canonical Event Model, `{ "event_type": ..., "payload": ... }`
    (01-overview.md, section 4.2).
  - The Header / Metadata / Payload split named explicitly in the
    Implementation Roadmap, Phase 4.
  - The field list already fixed by adapter-signal-contract.md
    (event_type, payload, timestamp).

Every place this schema is used is confined to this file plus
transport/serializer.py, by design (10-transport-layer.md, section 6:
"If a future version sends data over gRPC instead of HTTP, the Builder
changes zero lines"). Confirm the real shape against the Laravel backend's
validation rules and adjust only these two files if it differs.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Dict, List


@dataclass(frozen=True)
class EventHeader:
    """The `header` section of a wire-format Event
    (docs/03-specifications/02-EVENT_DICTIONARY.md, "Every Event contains:
    Header, Payload"; backend/app/Modules/Observation/Domain/
    ObservationValidator.php requires `header.event_type` — one of the ten
    canonical Event Dictionary values, enforced upstream at EventSignal
    construction, see pipeline/events.py — and `header.timestamp`).

    Deliberately carries no `sequence` field: ordering is guaranteed by
    array position within the Observation's `events` array (see
    contracts/adapter-signal-contract.md, section 2a), which is what both
    the Backend's validator and the ML Service actually rely on. An
    explicit sequence field was considered and deliberately not added —
    array position alone is sufficient and this keeps the Header minimal.
    """

    event_type: str
    timestamp: str  # ISO 8601, e.g. "2026-08-01T10:15:32Z"

    def to_dict(self) -> Dict[str, Any]:
        return {
            "event_type": self.event_type,
            "timestamp": self.timestamp,
        }


@dataclass(frozen=True)
class Event:
    """A single occurrence within an Observation — the real wire-format
    shape (docs/03-specifications/02-EVENT_DICTIONARY.md,
    03-ASES_JSON_SCHEMA.md): a nested `header`/`payload` object, not the
    flat `{event_type, payload}` shape `01-overview.md §4.2` previously,
    incorrectly, documented as final (RC-7, PIPELINE-002/WALK-008)."""

    header: EventHeader
    payload: Dict[str, Any]

    def to_dict(self) -> Dict[str, Any]:
        return {
            "header": self.header.to_dict(),
            "payload": self.payload,
        }


@dataclass(frozen=True)
class Context:
    """The Observation's `context` section — describes the execution
    environment (docs/03-specifications/03-ASES_JSON_SCHEMA.md, "Context").
    Required by the Backend's ObservationValidator: `framework`,
    `execution_start_time`, `execution_finish_time` (validated in that
    order, before Events or Metadata are ever checked)."""

    framework: str
    environment: str
    execution_start_time: str
    execution_finish_time: str

    def to_dict(self) -> Dict[str, Any]:
        return {
            "framework": self.framework,
            "environment": self.environment,
            "execution_start_time": self.execution_start_time,
            "execution_finish_time": self.execution_finish_time,
        }


@dataclass(frozen=True)
class ObservationMetadata:
    """Everything about an Observation that is not itself an Event.

    `spec_version` and `sdk_version` are both required by the Backend's
    ObservationValidator (PIPELINE-005/WALK-008) — previously undocumented
    and unpopulated anywhere in this SDK."""

    spec_version: str
    sdk_version: str
    environment: str
    started_at: str
    completed_at: str
    completion_reason: str
    event_count: int

    def to_dict(self) -> Dict[str, Any]:
        return {
            "spec_version": self.spec_version,
            "sdk_version": self.sdk_version,
            "environment": self.environment,
            "started_at": self.started_at,
            "completed_at": self.completed_at,
            "completion_reason": self.completion_reason,
            "event_count": self.event_count,
        }


@dataclass(frozen=True)
class Observation:
    """The complete execution story of a single Agent Task
    (09-observation-lifecycle.md, section 1).

    Deliberately carries no observation_id or task_id in its wire-format
    JSON: correlation is internal-only, via runtime_context, and never part
    of the outward-facing wire format (09-observation-lifecycle.md, section
    5). SentinelX assigns its own identifier on receipt.

    `correlation_id` (below) does NOT contradict this — it is the
    Collector's own internal grouping key (the same value
    observation/collector.py's `_correlation_key()` computes from
    runtime_context), kept on this object purely so a permanently-dropped
    Observation's log entry can reference which execution it was (RC-9,
    RELIABILITY-005). `to_dict()` deliberately excludes it — it never
    reaches the Backend.
    """

    context: Context
    events: List[Event]
    metadata: ObservationMetadata
    correlation_id: str = ""

    def to_dict(self) -> Dict[str, Any]:
        return {
            "context": self.context.to_dict(),
            "events": [event.to_dict() for event in self.events],
            "metadata": self.metadata.to_dict(),
        }
