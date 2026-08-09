<?php

use App\Modules\Agent\Domain\AgentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('framework');
            $table->string('framework_version')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default(AgentStatus::Active->value);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });

        // Composite index with an explicit DESC clause to serve
        // `GET /api/v1/agents` (filter by organization, sort by newest first).
        DB::statement('CREATE INDEX agents_organization_id_created_at_index ON agents (organization_id, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
