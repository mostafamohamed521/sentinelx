<?php

use App\Modules\Audit\Domain\ActorType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The only new table in this entire documentation series — see
 * docs/backend/audit-settings/adr/ADR-001-new-audit-logs-table.md.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('actor_type', 20)->default(ActorType::User->value);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('resource_type');
            $table->uuid('resource_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            // No `updated_at` — an audit log entry is written once and
            // never modified. Serves GET /audit-logs' organization-scoped,
            // most-recent-first listing, and its actor/action/resource_type
            // filters.
            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'actor_id']);
            $table->index(['organization_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
