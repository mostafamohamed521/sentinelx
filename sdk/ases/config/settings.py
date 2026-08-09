"""Settings — the sole reader of environment configuration.

Per environment-configuration.md ("Exactly one component reads environment
variables directly: config/settings.py") and 08-internal-architecture.md
("Configuration — a Service. Every component reads it; none of them modify
it."), no other module in this package is permitted to call os.environ or
os.getenv directly.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Optional

from ases.shared.constants import DEFAULT_ASES_ENDPOINT, DEFAULT_ENVIRONMENT
from ases.shared.exceptions import ConfigurationError

# Module-level state backing the optional configure() call
# (public-api-contract.md, section 5). Deliberately simple module globals,
# not a class — there is exactly one process-wide configuration, matching
# "Configuration happens once" in 12-packaging-and-distribution.md.
_global_settings: Optional["Settings"] = None
_configure_called = False


@dataclass(frozen=True)
class Settings:
    api_key: str
    endpoint: str = DEFAULT_ASES_ENDPOINT
    environment: str = DEFAULT_ENVIRONMENT

    @staticmethod
    def resolve(
        api_key: Optional[str] = None,
        endpoint: Optional[str] = None,
        environment: Optional[str] = None,
    ) -> "Settings":
        """Resolve Settings using the documented precedence order
        (environment-configuration.md, section 3):

            1. Explicit argument
            2. The matching ASES_* environment variable
            3. The built-in default (endpoint/environment only)

        Raises ConfigurationError immediately if api_key cannot be resolved
        from any source — the SDK must never silently start unconfigured
        (environment-configuration.md, section 6).
        """
        resolved_api_key = api_key or os.environ.get("ASES_API_KEY")
        if not resolved_api_key:
            raise ConfigurationError(
                "ASES api_key is required. Pass it explicitly to "
                "ASES(api_key=...) or configure(api_key=...), or set the "
                "ASES_API_KEY environment variable."
            )

        resolved_endpoint = (
            endpoint or os.environ.get("ASES_ENDPOINT") or DEFAULT_ASES_ENDPOINT
        )
        resolved_environment = (
            environment or os.environ.get("ASES_ENVIRONMENT") or DEFAULT_ENVIRONMENT
        )

        return Settings(
            api_key=resolved_api_key,
            endpoint=resolved_endpoint,
            environment=resolved_environment,
        )

    @staticmethod
    def resolve_for_instance(api_key: Optional[str]) -> "Settings":
        """Used by ASES.__init__(): an explicit constructor argument
        outranks a prior configure() call, which outranks environment
        variables (environment-configuration.md, section 3)."""
        if api_key is not None:
            return Settings.resolve(api_key=api_key)
        if _global_settings is not None:
            return _global_settings
        return Settings.resolve()


def configure_global(
    api_key: Optional[str] = None,
    endpoint: Optional[str] = None,
    environment: Optional[str] = None,
) -> None:
    """Backing implementation for the public ases.configure() function.

    May be called at most once per process — calling it twice raises a
    clear ConfigurationError rather than silently overwriting the prior
    call (environment-configuration.md, section 4).
    """
    global _global_settings, _configure_called
    if _configure_called:
        raise ConfigurationError(
            "configure() may only be called once per process. "
            "It has already been called."
        )
    _global_settings = Settings.resolve(
        api_key=api_key, endpoint=endpoint, environment=environment
    )
    _configure_called = True


def get_global_settings() -> Optional[Settings]:
    return _global_settings
