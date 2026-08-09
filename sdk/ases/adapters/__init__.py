"""Adapter exports — only Adapters actually implemented in this build are
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

from ases.adapters.generic.adapter import Execution, GenericAdapter

__all__ = ["CrewAIAdapter", "GenericAdapter", "Execution"]


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
    raise AttributeError(f"module {__name__!r} has no attribute {name!r}")
