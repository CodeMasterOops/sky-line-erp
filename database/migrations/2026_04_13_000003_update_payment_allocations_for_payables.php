<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->string('payable_type')->nullable()->after('payment_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
        });

        DB::table('payment_allocations')
            ->whereNotNull('bill_id')
            ->update([
                'payable_type' => 'bill',
                'payable_id' => DB::raw('bill_id'),
            ]);

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['bill_id']);
            $table->dropColumn('bill_id');
            $table->index(['payable_type', 'payable_id'], 'payment_allocations_payable_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->constrained()->cascadeOnDelete();
        });

        DB::table('payment_allocations')
            ->where('payable_type', 'bill')
            ->update([
                'bill_id' => DB::raw('payable_id'),
            ]);

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropIndex('payment_allocations_payable_idx');
            $table->dropColumn(['payable_type', 'payable_id']);
        });
    }
};
