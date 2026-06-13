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
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->foreignId('receipt_allocation_id')->nullable()->after('journal_id')->constrained('receipt_allocations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropForeign(['receipt_allocation_id']);
            $table->dropColumn('receipt_allocation_id');
        });
    }
};
