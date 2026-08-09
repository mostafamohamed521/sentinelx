<?php

use App\Modules\Audit\Domain\ActorType;
use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can list audit logs for their organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    AuditLog::factory(3)->create(['organization_id' => $organization->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id', 'metadata', 'created_at']],
            'pagination' => ['page', 'per_page', 'total_items', 'total_pages'],
        ]);
});

test('admin can view a single audit log entry', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->for($organization)->create();
    $entry = AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'agent.created']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($admin))
        ->getJson("/api/v1/audit-logs/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.action', 'agent.created');
});

test('audit logs can be filtered by actor_id, action, and resource_type', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $actor = User::factory()->for($organization)->create();

    AuditLog::factory()->create(['organization_id' => $organization->id, 'actor_id' => $actor->id, 'action' => 'agent.created', 'resource_type' => 'Agent']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'alert.resolved', 'resource_type' => 'Alert']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/audit-logs?actor_id={$actor->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actor_id', $actor->id);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/audit-logs?action=alert.resolved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'alert.resolved');

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/audit-logs?resource_type=Agent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.resource_type', 'Agent');
});

// === EDGE CASE ===

test('a non-existent audit log id returns 404', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/audit-logs/0198a1b2-0000-7000-8000-000000000000')
        ->assertNotFound();
});

test('audit_logs rows can never be updated or deleted — no such route exists', function () {
    $methods = collect(app('router')->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'audit-logs'))
        ->flatMap(fn ($route) => $route->methods())
        ->unique()
        ->values();

    expect($methods->contains('PUT'))->toBeFalse()
        ->and($methods->contains('PATCH'))->toBeFalse()
        ->and($methods->contains('DELETE'))->toBeFalse()
        ->and($methods->contains('POST'))->toBeFalse();
});

// === BUSINESS RULE ===

test('system-actor audit entries have a null actor_id, never a fabricated one', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    $entry = AuditLog::factory()->system()->create(['organization_id' => $organization->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson("/api/v1/audit-logs/{$entry->id}")
        ->assertOk()
        ->assertJsonPath('data.actor_type', ActorType::System->value)
        ->assertJsonPath('data.actor_id', null);
});

// === AUTHORIZATION (asymmetric — Member excluded, Admin included) ===

test('a member cannot list or view audit logs', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();
    $entry = AuditLog::factory()->create(['organization_id' => $organization->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson('/api/v1/audit-logs')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson("/api/v1/audit-logs/{$entry->id}")
        ->assertForbidden();
});

test('an admin can list and view audit logs — confirms Admin is not excluded, only Member', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->for($organization)->create();
    AuditLog::factory()->create(['organization_id' => $organization->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($admin))
        ->getJson('/api/v1/audit-logs')
        ->assertOk();
});

test('an unauthenticated request cannot access audit logs', function () {
    $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
});

test('an agent (API key) cannot access audit logs', function () {
    $rawKey = 'sk_live_audit_test';
    createAgentWithKey($rawKey);

    $this->withHeader('X-Api-Key', $rawKey)
        ->getJson('/api/v1/audit-logs')
        ->assertUnauthorized();
});

// === DATA ISOLATION ===

test('organization As audit logs never include organization Bs entries', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();

    AuditLog::factory()->create(['organization_id' => $organizationA->id]);
    AuditLog::factory(3)->create(['organization_id' => $organizationB->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('an owner cannot view another organizations audit log entry by id', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();
    $entryB = AuditLog::factory()->create(['organization_id' => $organizationB->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson("/api/v1/audit-logs/{$entryB->id}")
        ->assertNotFound();
});
