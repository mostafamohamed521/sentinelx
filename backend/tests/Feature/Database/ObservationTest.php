<?php

use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Analysis\Infrastructure\Persistence\Prediction;
use App\Modules\Observation\Domain\AnalysisStatus;
use App\Modules\Observation\Infrastructure\Persistence\Observation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// === HAPPY PATH ===

test('an observation defaults to PENDING and stores raw_ases_json as an array', function () {
    $observation = Observation::factory()->create();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Pending)
        ->and($observation->raw_ases_json)->toBeArray()
        ->and($observation->raw_ases_json)->toHaveKeys(['context', 'events', 'metadata']);
});

test('an observation can be marked completed', function () {
    $observation = Observation::factory()->completed()->create();

    expect($observation->analysis_status)->toBe(AnalysisStatus::Completed)
        ->and($observation->processed_at)->not->toBeNull();
});

// === BUSINESS RULE: organization_id denormalization (ADR-005) ===

test('organization_id always matches the owning agent\'s organization', function () {
    $agent = Agent::factory()->create();
    $observation = Observation::factory()->for($agent)->create();

    expect($observation->organization_id)->toBe($agent->organization_id);
});

// === RELATIONSHIPS ===

test('an observation belongs to an organization and an agent', function () {
    $observation = Observation::factory()->create();

    expect($observation->organization())->toBeInstanceOf(BelongsTo::class)
        ->and($observation->agent())->toBeInstanceOf(BelongsTo::class);
});

// The Observation module never depends on Analysis — see
// 05-cross-module-boundaries.md §1. There is deliberately no
// Observation::prediction() relation; a fresh Observation simply has no
// matching row in `predictions`, verified from the owning (allowed)
// direction instead — Prediction belongs to Observation, never the reverse.
test('a fresh observation has no prediction', function () {
    $observation = Observation::factory()->create();

    expect(Prediction::where('observation_id', $observation->id)->exists())->toBeFalse();
});
