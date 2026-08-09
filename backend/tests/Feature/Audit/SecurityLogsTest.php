<?php

use App\Modules\Audit\Infrastructure\Persistence\AuditLog;
use App\Modules\Authentication\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Infrastructure\Persistence\Organization;

// === HAPPY PATH ===

test('owner can list security logs, containing only security-relevant actions', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'user.logged_in']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'api_key.rotated']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'organization.updated']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'agent.archived']);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/security-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $actions = collect($response->json('data'))->pluck('action');

    expect($actions)->toContain('user.logged_in')
        ->toContain('api_key.rotated')
        ->and($actions)->not->toContain('organization.updated')
        ->and($actions)->not->toContain('agent.archived');
});

// === EDGE CASE ===

test('security logs never return an organization.updated or agent.archived entry, even though both exist in the general Audit Log', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();

    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'organization.updated']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'agent.archived']);

    // Both are visible in the general Audit Log...
    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // ...but neither appears in Security Logs.
    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/security-logs')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('an action filter outside the security set narrows to zero results, never expanding beyond the fixed set', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'user.logged_in']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/security-logs?action=organization.updated')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('an action filter inside the security set narrows correctly', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->owner()->for($organization)->create();
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'user.logged_in']);
    AuditLog::factory()->create(['organization_id' => $organization->id, 'action' => 'api_key.rotated']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($owner))
        ->getJson('/api/v1/security-logs?action=user.logged_in')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'user.logged_in');
});

// === AUTHORIZATION ===

test('a member cannot view security logs', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($member))
        ->getJson('/api/v1/security-logs')
        ->assertForbidden();
});

test('an admin can view security logs', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->admin()->for($organization)->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($admin))
        ->getJson('/api/v1/security-logs')
        ->assertOk();
});

// === DATA ISOLATION ===

test('security logs are scoped to the callers own organization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $ownerA = User::factory()->owner()->for($organizationA)->create();

    AuditLog::factory()->create(['organization_id' => $organizationB->id, 'action' => 'user.logged_in']);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($ownerA))
        ->getJson('/api/v1/security-logs')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
