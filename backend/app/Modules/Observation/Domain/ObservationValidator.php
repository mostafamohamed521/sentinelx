<?php

namespace App\Modules\Observation\Domain;

use App\Modules\Observation\Domain\Exceptions\ObservationValidationFailedException;
use DateTimeImmutable;
use Throwable;

/**
 * Implements exactly the five structural checks from 04-validation.md §3 —
 * nothing more, nothing less (see adr/ADR-002-structural-validation-only.md).
 * Pure PHP: no Laravel, no Eloquent, no HTTP concepts. Deliberately keeps
 * all five checks together in one place rather than splitting Checks
 * 1/2/3/5 into a FormRequest, per 04-validation.md §4's explicit rationale
 * for not doing so.
 *
 * Fail-fast: throws on the first violated check, carrying exactly one
 * field/reason pair, matching the response shape in 04-validation.md §5.
 */
class ObservationValidator
{
    /**
     * PERF-004: a structural upper bound on payload size — no maximum
     * existed anywhere in the Backend or ML Service (see
     * docs/integration-audit/06-performance-assumptions-audit.md). An
     * adjustable engineering default, not a frozen business rule; large
     * enough for legitimate batched SDK submissions, small enough to bound
     * worst-case ML Service processing cost per Observation.
     */
    private const MAX_EVENTS = 1000;

    /**
     * The ten canonical event types — see 02-domain.md §2 and
     * docs.zip/03-specifications/02-EVENT_DICTIONARY.md.
     *
     * @var array<int, string>
     */
    private const CANONICAL_EVENT_TYPES = [
        'api_call',
        'file_access',
        'command_execution',
        'network_connection',
        'database_operation',
        'tool_execution',
        'memory_operation',
        'authentication',
        'configuration_change',
        'custom',
    ];

    /**
     * @param  array<string, mixed>  $payload  The already-decoded JSON body (Check 1
     *                                         — is this valid JSON at all — happens
     *                                         before this validator ever runs).
     *
     * @throws ObservationValidationFailedException
     */
    public function validate(array $payload): void
    {
        $this->validateContext($payload);
        $this->validateEvents($payload);
        $this->validateMetadata($payload);
    }

    // ======= Checks 2 & 3 (partial) — context =======

    private function validateContext(array $payload): void
    {
        $context = $payload['context'] ?? null;

        if (! is_array($context)) {
            throw new ObservationValidationFailedException('context', 'context is required and must be an object');
        }

        if (! $this->isNonEmptyString($context['framework'] ?? null)) {
            throw new ObservationValidationFailedException('context.framework', 'framework is required');
        }

        $start = $this->parseTimestamp($context['execution_start_time'] ?? null);

        if (! $start) {
            throw new ObservationValidationFailedException('context.execution_start_time', 'execution_start_time is required and must be an ISO 8601 timestamp');
        }

        $finish = $this->parseTimestamp($context['execution_finish_time'] ?? null);

        if (! $finish) {
            throw new ObservationValidationFailedException('context.execution_finish_time', 'execution_finish_time is required and must be an ISO 8601 timestamp');
        }

        if ($finish < $start) {
            throw new ObservationValidationFailedException('context.execution_finish_time', 'execution_finish_time must not be before execution_start_time');
        }
    }

    // ======= Checks 2, 3, 4 & 5 — events =======

    private function validateEvents(array $payload): void
    {
        $events = $payload['events'] ?? null;

        if (! is_array($events) || ! array_is_list($events)) {
            throw new ObservationValidationFailedException('events', 'events is required and must be an array');
        }

        if (count($events) < 1) {
            throw new ObservationValidationFailedException('events', 'at least one event is required');
        }

        if (count($events) > self::MAX_EVENTS) {
            throw new ObservationValidationFailedException('events', 'events must not exceed '.self::MAX_EVENTS.' per Observation');
        }

        $previousTimestamp = null;

        foreach ($events as $index => $event) {
            $timestamp = $this->validateEvent($event, $index);

            if ($previousTimestamp !== null && $timestamp < $previousTimestamp) {
                throw new ObservationValidationFailedException(
                    "events[{$index}].header.timestamp",
                    'events must be in chronological order'
                );
            }

            $previousTimestamp = $timestamp;
        }
    }

    private function validateEvent(mixed $event, int $index): DateTimeImmutable
    {
        if (! is_array($event)) {
            throw new ObservationValidationFailedException("events[{$index}]", 'each event must be an object');
        }

        $header = $event['header'] ?? null;

        if (! is_array($header)) {
            throw new ObservationValidationFailedException("events[{$index}].header", 'header is required and must be an object');
        }

        $eventType = $header['event_type'] ?? null;

        if (! in_array($eventType, self::CANONICAL_EVENT_TYPES, true)) {
            throw new ObservationValidationFailedException(
                "events[{$index}].header.event_type",
                'event_type must be one of the canonical Event Dictionary types'
            );
        }

        $timestamp = $this->parseTimestamp($header['timestamp'] ?? null);

        if (! $timestamp) {
            throw new ObservationValidationFailedException(
                "events[{$index}].header.timestamp",
                'timestamp is required and must be an ISO 8601 timestamp'
            );
        }

        // Payload content is never validated beyond "is an object" — see
        // adr/ADR-002-structural-validation-only.md.
        if (! is_array($event['payload'] ?? null)) {
            throw new ObservationValidationFailedException("events[{$index}].payload", 'payload is required and must be an object');
        }

        return $timestamp;
    }

    // ======= Checks 2 & 3 (partial) — metadata =======

    private function validateMetadata(array $payload): void
    {
        $metadata = $payload['metadata'] ?? null;

        if (! is_array($metadata)) {
            throw new ObservationValidationFailedException('metadata', 'metadata is required and must be an object');
        }

        if (! $this->isNonEmptyString($metadata['spec_version'] ?? null)) {
            throw new ObservationValidationFailedException('metadata.spec_version', 'spec_version is required');
        }

        if (! $this->isNonEmptyString($metadata['sdk_version'] ?? null)) {
            throw new ObservationValidationFailedException('metadata.sdk_version', 'sdk_version is required');
        }
    }

    // ======= Helpers =======

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    private function parseTimestamp(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
