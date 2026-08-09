<?php

namespace App\Modules\Analysis\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by MLClient for any genuine transport failure — timeout, connection
 * refused, or a non-2xx response from the ML Engine. Never rendered to
 * HTTP (this module owns no public route); caught by AnalyzeObservationAction
 * and left to propagate out of the Job so Laravel's own retry/backoff
 * mechanism can absorb transient failures, per
 * adr/ADR-003-ml-failure-retry-then-fail.md. Only once retries are exhausted
 * does the Job's failed() hook translate this into markFailed().
 */
class MLCommunicationException extends RuntimeException {}
