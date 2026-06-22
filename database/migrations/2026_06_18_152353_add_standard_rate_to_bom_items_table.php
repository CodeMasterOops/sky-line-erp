<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            // Cost per unit for labour/overhead items (e.g. £/hour, £/run).
            // Materials use actual stock layer costs; this field covers non-stock items.
            $table->decimal('standard_rate', 16, 4)->nullable()->after('wastage_pct');
        });
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropColumn('standard_rate');
        });
    }
};
