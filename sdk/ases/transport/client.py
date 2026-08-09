"""API Client — the only component that actually knows how to speak HTTP:
authentication, requests, retry policy, endpoint management
(10-transport-layer.md, section 7).

Built on Python's standard library (urllib.request) rather than a
third-party HTTP library, consistent with the Dependency Policy in
12-packaging-and-distribution.md ("every dependency must have a clear,
articulable reason for existing") — a low-volume POST with a short timeout
does not need more than the standard library already provides. This keeps
the shipped package at zero third-party runtime dependencies.

The request shape below — POST {endpoint}{OBSERVATIONS_PATH} with an
`X-API-Key: <api_key>` header, expecting HTTP 202 on success — is confirmed
directly against the real Laravel backend's Agent guard
(backend/app/Modules/Authentication/AuthServiceProvider.php:34-39), closing
RC-8's IDENTITY-001 (previously an unconfirmed assumption, self-flagged in
sdk/README.md's own Compliance Notes).
"""

from __future__ import annotations

import time
import urllib.error
import urllib.request
from typing import Dict, Optional

from ases.config.settings import Settings
from ases.shared.constants import (
    API_KEY_HEADER,
    AUTH_FAILURE_STATUS_CODES,
    HTTP_REQUEST_TIMEOUT_SECONDS,
    OBSERVATIONS_PATH,
    RATE_LIMIT_STATUS_CODE,
    SDK_NAME,
    SDK_VERSION,
    TRANSPORT_MAX_RETRIES,
)
from ases.shared.logger import get_logger


class APIClient:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._logger = get_logger("transport.client")

    def send(self, serialized_observation: str) -> bool:
        """Attempt delivery with up to TRANSPORT_MAX_RETRIES retries.

        Returns True on success (HTTP 202), False once retries are
        exhausted or the failure is not retry-worthy. Never raises — this
        method is the concrete enforcement point for ADR-004's "ASES must
        never be the reason an Agent fails."

        Three failure classes are handled distinctly (RC-8, IDENTITY-002),
        not uniformly:
          - Authentication (401/403): fails fast, no retry — a bad
            credential cannot succeed on attempt 2 or 3.
          - Rate limit (429): retried, honoring a Retry-After header if the
            Backend supplies one, falling back to the standard backoff
            otherwise.
          - Everything else (other 4xx, 5xx, network/timeout errors):
            unchanged from the original policy — other 4xx fail fast
            (a validation/payload problem, not something retrying fixes),
            5xx/network errors use the standard bounded backoff.
        """
        url = self._settings.endpoint.rstrip("/") + OBSERVATIONS_PATH
        data = serialized_observation.encode("utf-8")
        headers = self._build_headers()

        attempt = 0
        while attempt <= TRANSPORT_MAX_RETRIES:
            attempt += 1
            request = urllib.request.Request(url, data=data, headers=headers, method="POST")
            retry_after: Optional[float] = None
            try:
                with urllib.request.urlopen(request, timeout=HTTP_REQUEST_TIMEOUT_SECONDS) as response:
                    status = response.getcode()
                    if status == 202:
                        return True
                    self._logger.warning(
                        "SentinelX responded with unexpected status %s (attempt %d/%d).",
                        status, attempt, TRANSPORT_MAX_RETRIES + 1,
                    )
            except urllib.error.HTTPError as exc:
                if exc.code in AUTH_FAILURE_STATUS_CODES:
                    # Never log the api_key itself here or anywhere else
                    # (RC-8, IDENTITY-004) — only the fact that it was
                    # rejected.
                    self._logger.warning(
                        "Observation dropped: authentication failed (HTTP %s) — "
                        "check your ASES_API_KEY configuration.",
                        exc.code,
                    )
                    return False
                if exc.code == RATE_LIMIT_STATUS_CODE:
                    retry_after = self._parse_retry_after(exc)
                    self._logger.warning(
                        "SentinelX rate-limited this Observation: HTTP 429 (attempt %d/%d).",
                        attempt, TRANSPORT_MAX_RETRIES + 1,
                    )
                elif 400 <= exc.code < 500:
                    self._logger.warning(
                        "SentinelX rejected the Observation: HTTP %s (attempt %d/%d).",
                        exc.code, attempt, TRANSPORT_MAX_RETRIES + 1,
                    )
                    # A non-429, non-auth 4xx (bad payload, validation
                    # failure) will not succeed on retry — fail fast rather
                    # than burning the remaining retry budget.
                    return False
                else:
                    self._logger.warning(
                        "SentinelX responded with a server error: HTTP %s (attempt %d/%d).",
                        exc.code, attempt, TRANSPORT_MAX_RETRIES + 1,
                    )
            except (urllib.error.URLError, TimeoutError, OSError) as exc:
                self._logger.warning(
                    "Network error delivering Observation: %s (attempt %d/%d).",
                    exc, attempt, TRANSPORT_MAX_RETRIES + 1,
                )

            if attempt <= TRANSPORT_MAX_RETRIES:
                if retry_after is not None:
                    time.sleep(retry_after)
                else:
                    # Simple bounded backoff: 1s, 2s, 4s, capped at 8s.
                    time.sleep(min(2 ** (attempt - 1), 8))

        return False

    def _parse_retry_after(self, exc: urllib.error.HTTPError) -> Optional[float]:
        """Reads the standard Retry-After header (seconds form only — the
        HTTP-date form is not used by this Backend's rate limiter). Falls
        back to None (the caller then uses the standard backoff) if the
        header is absent or not a plain number."""
        headers = exc.headers
        value = headers.get("Retry-After") if headers is not None else None
        if value is None:
            return None
        try:
            return float(value)
        except (TypeError, ValueError):
            return None

    def _build_headers(self) -> Dict[str, str]:
        return {
            "Content-Type": "application/json",
            # Required for the Backend to render an authentication failure
            # as a clean 401 JSON error (RC-8, discovered during this
            # phase's own live verification): without this, Laravel's
            # default unauthenticated() handler assumes a browser request
            # and attempts to redirect to a named "login" route that
            # doesn't exist in this API-only app, producing a 500/503
            # instead — masking the very failure Fix 2's classification
            # depends on being able to observe.
            "Accept": "application/json",
            API_KEY_HEADER: self._settings.api_key,
            "User-Agent": f"{SDK_NAME}-python/{SDK_VERSION}",
            "X-ASES-Environment": self._settings.environment,
        }
