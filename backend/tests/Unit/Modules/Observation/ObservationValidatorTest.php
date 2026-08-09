<?php

use App\Modules\Observation\Domain\Exceptions\ObservationValidationFailedException;
use App\Modules\Observation\Domain\ObservationValidator;

beforeEach(function () {
    $this->validator = new ObservationValidator;
});

test('a well-formed payload passes validation', function () {
    expect(fn () => $this->validator->validate(validAsesPayload()))->not->toThrow(ObservationValidationFailedException::class);
});

test('a payload with zero events is rejected', function () {
    expect(fn () => $this->validator->validate(validAsesPayload(['events' => []])))
        ->toThrow(ObservationValidationFailedException::class);
});

// === PERF-004: an unbounded event count is rejected ===

test('a payload with exactly the maximum allowed event count (1000) is accepted', function () {
    $events = array_fill(0, 1000, [
        'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T09:59:52Z'],
        'payload' => ['url' => 'https://api.example.com/v1/data', 'method' => 'GET'],
    ]);

    expect(fn () => $this->validator->validate(validAsesPayload(['events' => $events])))
        ->not->toThrow(ObservationValidationFailedException::class);
});

test('a payload exceeding the maximum allowed event count (1001) is rejected', function () {
    $events = array_fill(0, 1001, [
        'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T09:59:52Z'],
        'payload' => ['url' => 'https://api.example.com/v1/data', 'method' => 'GET'],
    ]);

    expect(fn () => $this->validator->validate(validAsesPayload(['events' => $events])))
        ->toThrow(ObservationValidationFailedException::class);
});

test('a payload with out-of-order event timestamps is rejected', function () {
    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['url' => 'https://api.example.com', 'method' => 'GET'],
            ],
            [
                'header' => ['event_type' => 'file_access', 'timestamp' => '2026-07-29T09:59:00Z'],
                'payload' => ['path' => '/tmp/file.txt'],
            ],
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});

test('chronologically ordered events with equal timestamps are accepted', function () {
    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['url' => 'https://api.example.com', 'method' => 'GET'],
            ],
            [
                'header' => ['event_type' => 'file_access', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['path' => '/tmp/file.txt'],
            ],
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->not->toThrow(ObservationValidationFailedException::class);
});

test('a missing context.framework field is rejected', function () {
    $payload = validAsesPayload();
    unset($payload['context']['framework']);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});

test('an execution_finish_time before execution_start_time is rejected', function () {
    $payload = validAsesPayload([
        'context' => [
            'execution_start_time' => '2026-07-29T10:00:00Z',
            'execution_finish_time' => '2026-07-29T09:00:00Z',
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});

test('an event_type outside the canonical event dictionary is rejected', function () {
    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => 'totally_made_up_event', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['foo' => 'bar'],
            ],
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});

test('every canonical event type is accepted', function (string $eventType) {
    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => $eventType, 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => ['foo' => 'bar'],
            ],
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->not->toThrow(ObservationValidationFailedException::class);
})->with([
    'api_call', 'file_access', 'command_execution', 'network_connection', 'database_operation',
    'tool_execution', 'memory_operation', 'authentication', 'configuration_change', 'custom',
]);

test('an event payload that is not an object is rejected', function () {
    $payload = validAsesPayload([
        'events' => [
            [
                'header' => ['event_type' => 'api_call', 'timestamp' => '2026-07-29T10:00:00Z'],
                'payload' => 'not-an-object',
            ],
        ],
    ]);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});

test('a missing metadata.spec_version field is rejected', function () {
    $payload = validAsesPayload();
    unset($payload['metadata']['spec_version']);

    expect(fn () => $this->validator->validate($payload))->toThrow(ObservationValidationFailedException::class);
});
