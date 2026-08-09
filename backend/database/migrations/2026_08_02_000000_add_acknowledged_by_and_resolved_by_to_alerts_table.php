<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small, additive change to the existing (frozen) `alerts` table — the
 * same pattern already used by
 * 2026_07_29_004816_add_email_verified_at_to_users_table.php. The base
 * table isn't recreated or altered beyond adding these two nullable
 * columns, which docs/backend/alert/02-domain.md §7 confirms are implied
 * by "acknowledge"/"resolve" being Human actions, even though the original
 * `alerts` migration predates that module's own documentation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->foreignUuid('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->after('resolved_at')->constrained('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropConstrainedForeignId('resolved_by');
        });
    }
};
