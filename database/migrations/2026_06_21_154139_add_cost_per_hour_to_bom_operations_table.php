<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bom_operations', function (Blueprint $table) {
            // Work-centre labour rate. Operation labour cost = duration_minutes/60 × cost_per_hour,
            // absorbed into WIP alongside BOM-line labour/overhead (same conversion mechanism).
            $table->decimal('cost_per_hour', 15, 4)->default(0)->after('duration_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bom_operations', function (Blueprint $table) {
            $table->dropColumn('cost_per_hour');
        });
    }
};
