"""The ASES SDK's sole Logger — a shared Service, per 08-internal-architecture.md
("Logger — a Service. Not part of the Pipeline.").

No component in this codebase configures its own logging.Logger instance or
falls back to a bare print() call (13-implementation-roadmap.md, Phase 3).
Every other module imports get_logger() from here instead.

Because this SDK runs embedded inside a customer's own process, it is
deliberately careful not to touch the customer's root logging configuration:
it attaches its own handler only to the "ases" logger namespace and disables
propagation, so it never doubles up with — or is silently swallowed by — the
host application's own logging setup.
"""

from __future__ import annotations

import logging
import sys

_CONFIGURED = False


def _configure_root_handler() -> None:
    global _CONFIGURED
    if _CONFIGURED:
        return
    handler = logging.StreamHandler(stream=sys.stderr)
    formatter = logging.Formatter(
        fmt="[%(asctime)s] %(levelname)-8s ases.%(name)s: %(message)s",
        datefmt="%Y-%m-%dT%H:%M:%S%z",
    )
    handler.setFormatter(formatter)
    root = logging.getLogger("ases")
    root.addHandler(handler)
    root.setLevel(logging.INFO)
    root.propagate = False
    _CONFIGURED = True


def get_logger(name: str) -> logging.Logger:
    """Return a namespaced logger, e.g. get_logger("transport.worker")
    resolves to the "ases.transport.worker" logger."""
    _configure_root_handler()
    return logging.getLogger(f"ases.{name}")
