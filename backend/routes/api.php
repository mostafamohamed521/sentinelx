<?php

use App\Modules\Agent\API\Controllers\AgentController;
use App\Modules\Agent\API\Middleware\EnsureOwnerRole;
use App\Modules\Alert\API\Controllers\AlertController;
use App\Modules\Audit\API\Controllers\AuditController;
use App\Modules\Audit\API\Controllers\SecurityLogController;
use App\Modules\Audit\API\Middleware\EnsureOwnerOrAdminRole;
use App\Modules\Authentication\ApiKey\API\Controllers\ApiKeyController;
use App\Modules\Authentication\Identity\API\Controllers\AuthController;
use App\Modules\Authentication\Identity\API\Controllers\EmailVerificationController;
use App\Modules\Dashboard\API\Controllers\DashboardController;
use App\Modules\Observation\API\Controllers\AgentObservationController;
use App\Modules\Observation\API\Controllers\ObservationController;
use App\Modules\Organization\API\Controllers\OrganizationController;
use App\Modules\Organization\API\Middleware\EnsureOwnerRole as OrganizationEnsureOwnerRole;
use Illuminate\Support\Facades\Route;

// ======= Auth Routes (moved under v1 — see integration audit CONTRACT-005:
// every other module is v1-prefixed and the Frontend already assumes v1
// universally, so Auth was the one inconsistent exception) =======

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');

    Route::get('/auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:api')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::post('/auth/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:auth-email-resend');
    });
});

// ======= Agent Self Routes =======

// An authenticated Agent's one Capability is Submit Observation
// (06-authorization.md §12) — the "agent" guard succeeding already implies
// it, so no separate capability middleware exists for a single capability.
Route::middleware('auth:agent')->group(function () {
    Route::get('/agent/me', [AgentController::class, 'me']);

    // Owned by the Observation module. API Key guard only — a Human,
    // regardless of Role, can never submit an Observation; there is no
    // Role check to write here at all, the JWT guard simply never matches
    // this route. See 06-authorization.md §2.
    //
    // SECURITY-002: the system's single highest-volume, externally-reachable
    // endpoint — previously the only unthrottled route in the entire API.
    Route::post('/v1/observations', [ObservationController::class, 'store'])
        ->middleware('throttle:observation-ingestion');
});

// ======= Agent Management Routes (Stage 2) =======

// Every route below requires the Human/JWT guard — an authenticated Agent
// can never reach these, per 05-authorization.md §3 (enforced at routing,
// not inside a permission check). Owner-only routes additionally require
// EnsureOwnerRole; Owner+Member routes only require authentication.
Route::prefix('v1')->middleware(['auth:api', 'throttle:api'])->group(function () {
    // ======= Profile Routes (Stage 7) =======

    // No Role gate at all — every authenticated Human always manages their
    // own profile. See 07-authorization.md §2. Deliberately distinct from
    // GET /v1/auth/me — this is a separate, documented v1 path, not a
    // rename of the existing route.
    Route::patch('/me', [AuthController::class, 'updateProfile']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);

    Route::get('/agents', [AgentController::class, 'index']);
    Route::get('/agents/{agentId}', [AgentController::class, 'show']);

    // Owned by the Observation module (confirmed in
    // docs/backend/agent/06-api-contract.md §7 as Observation-owned,
    // despite sharing the /agents URL prefix — route grouping ≠ module
    // ownership, same nuance as Stage 2's rotate-api-key).
    Route::get('/agents/{agentId}/observations', [AgentObservationController::class, 'index']);

    Route::middleware(EnsureOwnerRole::class)->group(function () {
        Route::post('/agents', [AgentController::class, 'store']);
        Route::patch('/agents/{agentId}', [AgentController::class, 'update']);
        Route::patch('/agents/{agentId}/archive', [AgentController::class, 'archive']);

        // Owned by the Authentication module's API Key submodule — see
        // 01-overview.md §5. Reuses EnsureOwnerRole because Authentication
        // is allowed to depend on Agent (05-module-dependencies.md §4).
        Route::post('/agents/{agentId}/rotate-api-key', [ApiKeyController::class, 'rotate']);
    });

    // ======= Observation Routes (Stage 3) =======

    // Owner, Admin, and Member all have identical read access — no mutation
    // is possible at all (Observations are immutable), so there is no Role
    // tier to restrict. See 06-authorization.md §2.
    Route::get('/observations', [ObservationController::class, 'index']);
    Route::get('/observations/{observationId}', [ObservationController::class, 'show']);

    // ======= Alert Routes (Stage 5) =======

    // Owner, Admin, and Member all have identical access to every action
    // here — a deliberate, reasoned gap-fill, not an oversight. See
    // 04-authorization.md and adr/ADR-003-all-roles-can-act-on-alerts.md.
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/{alertId}', [AlertController::class, 'show']);
    Route::patch('/alerts/{alertId}/acknowledge', [AlertController::class, 'acknowledge']);
    Route::patch('/alerts/{alertId}/resolve', [AlertController::class, 'resolve']);

    // ======= Dashboard Routes (Stage 6) =======

    // Owner, Admin, and Member all have identical, full access — every
    // value in the response is already independently visible to all three
    // Roles through its own underlying endpoint. See 05-authorization.md
    // and adr/ADR-003-all-roles-can-view-dashboard.md.
    Route::get('/dashboard', [DashboardController::class, 'show']);

    // ======= Organization Routes (Stage 7) =======

    // GET is Owner/Admin/Member (harmless to view); PATCH is Owner only —
    // the first genuinely asymmetric Role decision in this series. See
    // 07-authorization.md §3 and adr/ADR-004-audit-and-org-settings-restricted-access.md.
    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::patch('/organization', [OrganizationController::class, 'update'])
        ->middleware(OrganizationEnsureOwnerRole::class);

    // ======= Audit Routes (Stage 7) =======

    // Owner, Admin only — Member deliberately excluded, the first
    // Member-gets-403-on-a-GET decision in this series. See
    // 07-authorization.md §4 and adr/ADR-004-audit-and-org-settings-restricted-access.md.
    Route::middleware(EnsureOwnerOrAdminRole::class)->group(function () {
        Route::get('/audit-logs', [AuditController::class, 'index']);
        Route::get('/audit-logs/{auditLogId}', [AuditController::class, 'show']);
        Route::get('/security-logs', [SecurityLogController::class, 'index']);
    });
});
