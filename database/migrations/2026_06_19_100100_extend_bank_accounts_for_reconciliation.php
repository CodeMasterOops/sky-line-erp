<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('company_id')
                ->constrained('branches')
                ->nullOnDelete();
            $table->decimal('opening_balance', 15, 2)->default(0)->after('currency');
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
            $table->timestamp('last_reconciled_at')->nullable()->after('opening_balance_date');
            $table->decimal('last_reconciled_balance', 15, 2)->nullable()->after('last_reconciled_at');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'branch_id',
                'opening_balance',
                'opening_balance_date',
                'last_reconciled_at',
                'last_reconciled_balance',
            ]);
        });
    }
};
