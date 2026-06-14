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
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->decimal('tds_base_amount', 12, 2)->default(0)->after('tds_deducted');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->dropColumn('tds_base_amount');
        });
    }
};
