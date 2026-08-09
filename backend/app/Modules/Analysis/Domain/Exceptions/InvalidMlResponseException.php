<?php

namespace App\Modules\Analysis\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by MLResponseValidator when the ML Engine's response is missing a
 * required field or contains an out-of-range value — a contract violation,
 * not a transport failure. Never rendered to HTTP: this module owns no
 * public route (06-authorization.md §1), so this exception is always caught
 * internally by AnalyzeObservationAction and translated into
 * ObservationRepository::markFailed(), never bubbling past the Queue Worker.
 *
 * Deliberately not retried by the Job — see 04-ml-client-contract.md §5 and
 * adr/ADR-003-ml-failure-retry-then-fail.md: a malformed response is
 * deterministic, so retrying would reproduce the identical failure.
 */
class InvalidMlResponseException extends RuntimeException {}
