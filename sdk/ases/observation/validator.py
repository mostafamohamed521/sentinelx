"""Observation Validator — confirms a built Observation complies with the
ASES Schema before Transport ever sees it; rejects malformed Observations
(08-internal-architecture.md, section 5).

Raises ValidationError on failure. The only caller — ASES._on_observation_ready
in ases/__init__.py — catches this, logs a warning, and drops the
Observation, matching the documented failure path in
09-observation-lifecycle.md, section 8. This function itself never logs and
never swallows errors silently, so it stays trivially unit-testable.
"""

from __future__ import annotations

from ases.observation.models import Observation
from ases.shared.constants import CANONICAL_EVENT_TYPES, VALID_COMPLETION_REASONS
from ases.shared.exceptions import ValidationError


def validate_observation(observation: Observation) -> None:
    """Mirrors the Backend's real ObservationValidator's own check order —
    Context, then Events, then Metadata (RC-7) — so a rejection here means
    the same rejection would happen server-side, just caught earlier."""
    context = observation.context
    if context is None:
        raise ValidationError("Observation is missing context entirely.")
    if not context.framework or not isinstance(context.framework, str):
        raise ValidationError("context.framework is required.")
    if not context.execution_start_time or not context.execution_finish_time:
        raise ValidationError(
            "context.execution_start_time and context.execution_finish_time are both required."
        )

    if not observation.events:
        raise ValidationError("Observation has zero Events — nothing to send.")

    for index, event in enumerate(observation.events):
        header = event.header
        if header is None:
            raise ValidationError(f"Event at index {index} is missing a header.")
        if not header.event_type or header.event_type not in CANONICAL_EVENT_TYPES:
            raise ValidationError(
                f"Event at index {index} has an invalid header.event_type "
                f"(must be one of {sorted(CANONICAL_EVENT_TYPES)})."
            )
        if not header.timestamp or not isinstance(header.timestamp, str):
            raise ValidationError(f"Event at index {index} is missing a valid ISO 8601 timestamp.")
        if event.payload is None or not isinstance(event.payload, dict):
            raise ValidationError(f"Event at index {index} has an invalid payload (must be a dict).")

    metadata = observation.metadata
    if metadata is None:
        raise ValidationError("Observation is missing metadata entirely.")
    if not metadata.spec_version:
        raise ValidationError("metadata.spec_version is required.")
    if not metadata.sdk_version:
        raise ValidationError("metadata.sdk_version is required.")
    if metadata.event_count != len(observation.events):
        raise ValidationError(
            f"metadata.event_count ({metadata.event_count}) does not match "
            f"the actual number of Events ({len(observation.events)})."
        )
    if metadata.completion_reason not in VALID_COMPLETION_REASONS:
        raise ValidationError(
            f"metadata.completion_reason is invalid: {metadata.completion_reason!r}."
        )
    if not metadata.started_at or not metadata.completed_at:
        raise ValidationError("metadata.started_at and metadata.completed_at are both required.")
