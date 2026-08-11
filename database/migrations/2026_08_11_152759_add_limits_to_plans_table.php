<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Per-plan quotas beyond branches.
 *
 * Nullable and left null on every existing plan, so no tenant gains a limit it
 * did not have. `branch_limit` keeps its own column — it predates this and is
 * read through the same registry (config/limits.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('limits')->nullable()->after('branch_limit');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('limits');
        });
    }
};
