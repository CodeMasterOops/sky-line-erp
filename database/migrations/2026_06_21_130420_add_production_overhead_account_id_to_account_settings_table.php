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
        Schema::table('account_settings', function (Blueprint $table) {
            // Credit side of labour/overhead absorption into WIP (a "Production Overhead
            // Applied" / conversion-cost accrual account). When null, labour/overhead BOM
            // lines are not absorbed and finished goods are valued at material cost only.
            $table->foreignId('production_overhead_account_id')
                ->nullable()
                ->after('manufacturing_variance_account_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_overhead_account_id');
        });
    }
};
