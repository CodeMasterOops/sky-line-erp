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
            // Credit side of subcontract-charge absorption into WIP (a "Subcontract Charges
            // Accrued" / payable account). When null, the charge is not absorbed.
            $table->foreignId('subcontract_account_id')->nullable()
                ->after('production_overhead_account_id')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subcontract_account_id');
        });
    }
};
