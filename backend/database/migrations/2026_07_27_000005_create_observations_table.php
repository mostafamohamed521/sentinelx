<?php

use App\Modules\Observation\Domain\AnalysisStatus;
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
        Schema::create('observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Denormalized alongside agent_id — see ADR-005 (multi-tenancy).
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('agent_id')->constrained('agents')->restrictOnDelete();
            $table->string('analysis_status', 20)->default(AnalysisStatus::Pending->value);
            $table->jsonb('raw_ases_json');
            $table->timestamp('received_at');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // Composite indexes match documented access patterns exactly —
        // see backend/docs/database/02-schema/indexes.md.
        DB::statement('CREATE INDEX observations_agent_id_received_at_index ON observations (agent_id, received_at DESC)');
        DB::statement('CREATE INDEX observations_organization_id_received_at_index ON observations (organization_id, received_at DESC)');
        DB::statement('CREATE INDEX observations_analysis_status_received_at_index ON observations (analysis_status, received_at ASC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
