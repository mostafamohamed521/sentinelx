"""Serializer — converts an Observation object into JSON
(10-transport-layer.md, section 6: "Transport is what knows it's
specifically talking HTTP, and therefore Transport is what turns that
object into JSON.").

Kept independent from the Builder deliberately: if a future version speaks
gRPC instead of HTTP, only this file (and client.py) change — the Builder
changes zero lines.
"""

from __future__ import annotations

import json

from ases.observation.models import Observation


def serialize_observation(observation: Observation) -> str:
    return json.dumps(observation.to_dict(), separators=(",", ":"))
