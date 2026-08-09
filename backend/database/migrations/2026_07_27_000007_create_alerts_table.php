<?php

use App\Modules\Alert\Domain\AlertStatus;
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
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prediction_id')->unique()->constrained('predictions')->restrictOnDelete();
            $table->string('severity', 20);
            $table->string('status', 20)->default(AlertStatus::Open->value);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Serves the dashboard's "latest open alerts" query.
        DB::statement('CREATE INDEX alerts_status_created_at_index ON alerts (status, created_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
