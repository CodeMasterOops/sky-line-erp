<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_payments', function (Blueprint $table) {
            $table->foreignId('payment_mode_id')->nullable()->after('receipt_id')->constrained('payment_modes')->nullOnDelete();
            $table->string('payment_method', 50)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('receipt_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_mode_id']);
            $table->dropColumn('payment_mode_id');
            $table->string('payment_method', 50)->nullable(false)->default('cash')->change();
        });
    }
};
