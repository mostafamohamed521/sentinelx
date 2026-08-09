<?php

use App\Modules\Analysis\Domain\Exceptions\InvalidMlResponseException;
use App\Modules\Analysis\Domain\MLResponseValidator;

function validMlResponse(array $overrides = []): array
{
    return [
        'verdict' => 'SUSPICIOUS',
        'risk_score' => 62,
        'confidence' => 0.78,
        'summary' => 'Unusual outbound network call.',
        'model_version' => 'sentinelx-ml-1.4.2',
        'reasons' => ['unexpected destination host'],
        'evidence' => [['type' => 'event_reference', 'sequence' => 1]],
        ...$overrides,
    ];
}

// === HAPPY PATH ===

test('a fully valid ML response passes validation without throwing', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse());

    expect(true)->toBeTrue();
});

// === EDGE CASE / BUSINESS RULE ===

test('a response missing verdict is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['verdict' => null]));
})->throws(InvalidMlResponseException::class);

test('a response with a verdict outside the canonical enum is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['verdict' => 'UNKNOWN']));
})->throws(InvalidMlResponseException::class);

test('a response with risk_score above 100 is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['risk_score' => 101]));
})->throws(InvalidMlResponseException::class);

test('a response with risk_score below 0 is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['risk_score' => -1]));
})->throws(InvalidMlResponseException::class);

test('a response with confidence above 1 is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['confidence' => 1.5]));
})->throws(InvalidMlResponseException::class);

test('a response missing summary is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['summary' => '']));
})->throws(InvalidMlResponseException::class);

test('a response missing model_version is rejected', function () {
    $validator = new MLResponseValidator;

    $validator->validate(validMlResponse(['model_version' => null]));
})->throws(InvalidMlResponseException::class);
