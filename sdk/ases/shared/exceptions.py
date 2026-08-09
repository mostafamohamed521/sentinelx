"""Custom exception hierarchy for the ASES SDK.

Kept in shared/ (not scattered per-module) per ADR-006 — shared/ is
deliberately narrow (Logger, Constants, Exceptions only), never a generic
"utils" dumping ground.
"""

from __future__ import annotations


class ASESError(Exception):
    """Base class for every exception the ASES SDK raises intentionally."""


class ConfigurationError(ASESError):
    """Raised when the SDK cannot be configured correctly — e.g. no API key
    is available from any source (constructor argument, configure(), or the
    ASES_API_KEY environment variable). This is the one category of error
    the SDK is allowed to raise directly into customer code, because it
    happens at setup time, before any Agent Task is running — see
    environment-configuration.md, section 6 ("Failing fast... never allowing
    the SDK to silently start in an unconfigured state.").
    """


class AdapterError(ASESError):
    """Raised when an Adapter (including GenericAdapter) is used incorrectly
    by customer code — e.g. calling emit() before attach(), or supplying an
    invalid completion reason. Like ConfigurationError, this is a
    programmer-error signal raised at the point of misuse, not a runtime
    delivery failure — it must never be confused with a network or
    validation problem, which are handled silently per ADR-004.
    """


class ValidationError(ASESError):
    """Raised internally when a built Observation fails Schema validation.

    This exception is caught inside the SDK (see ASES._on_observation_ready
    in ases/__init__.py) and never propagates into customer code — the
    Observation is dropped with a logged warning instead, matching the
    documented failure path in 09-observation-lifecycle.md, section 8:
    'Collector finalizes the Observation anyway -> Builder attempts
    construction -> Validator rejects it -> Transport never sees it at all.'
    """


class TransportError(ASESError):
    """Reserved for internal Transport-layer failures that are handled
    entirely inside transport/client.py and transport/worker.py. Like
    ValidationError, this must never propagate to customer code — Transport
    is Passive by design (ADR-004) and is required to swallow delivery
    failures internally, log a warning, and drop the Observation after
    retries are exhausted.
    """
