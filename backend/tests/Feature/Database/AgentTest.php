<?php

use App\Modules\Agent\Domain\AgentStatus;
use App\Modules\Agent\Infrastructure\Persistence\Agent;
use App\Modules\Organization\Infrastructure\Persistence\Organization;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

// === HAPPY PATH ===

test('an agent belongs to an organization and defaults to ACTIVE', function () {
    $agent = Agent::factory()->create();

    expect($agent->status)->toBe(AgentStatus::Active)
        ->and($agent->organization)->toBeInstanceOf(Organization::class);
});

test('an agent can be archived', function () {
    $agent = Agent::factory()->archived()->create();

    expect($agent->status)->toBe(AgentStatus::Archived);
});

// === CONSTRAINTS ===

test('agent name must be unique within an organization', function () {
    $organization = Organization::factory()->create();
    Agent::factory()->for($organization)->create(['name' => 'Support Agent']);

    expect(fn () => Agent::factory()->for($organization)->create(['name' => 'Support Agent']))
        ->toThrow(QueryException::class);
});

test('two different organizations may each have an agent with the same name', function () {
    Agent::factory()->create(['name' => 'Support Agent']);
    $second = Agent::factory()->create(['name' => 'Support Agent']);

    expect($second->exists)->toBeTrue();
});

// === RELATIONSHIPS ===

test('an agent has many observations', function () {
    $agent = Agent::factory()->create();

    expect($agent->observations())->toBeInstanceOf(HasMany::class);
});
