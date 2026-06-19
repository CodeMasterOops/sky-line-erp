<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->foreignId('supplier_advance_account_id')->nullable()->after('customer_advance_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('suspense_account_id')->nullable()->after('supplier_advance_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('writeoff_account_id')->nullable()->after('suspense_account_id')->constrained('accounts')->nullOnDelete();
            $table->foreignId('rounding_account_id')->nullable()->after('writeoff_account_id')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_settings', function (Blueprint $table) {
            $table->dropForeign(['supplier_advance_account_id']);
            $table->dropForeign(['suspense_account_id']);
            $table->dropForeign(['writeoff_account_id']);
            $table->dropForeign(['rounding_account_id']);
            $table->dropColumn([
                'supplier_advance_account_id',
                'suspense_account_id',
                'writeoff_account_id',
                'rounding_account_id',
            ]);
        });
    }
};
