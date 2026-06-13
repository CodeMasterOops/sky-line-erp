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
            $table->foreignId('tds_id')->nullable()->after('amount')->constrained('taxes')->nullOnDelete();
            $table->decimal('tds_deducted', 12, 2)->default(0)->after('tds_id');
            $table->decimal('net_amount_received', 12, 2)->nullable()->after('tds_deducted');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->dropForeign(['tds_id']);
            $table->dropColumn(['tds_id', 'tds_deducted', 'net_amount_received']);
        });
    }
};
