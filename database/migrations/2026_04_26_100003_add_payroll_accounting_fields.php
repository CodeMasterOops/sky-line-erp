<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('processed_at')
                ->constrained('journals')->nullOnDelete();
            $table->foreignId('paid_account_id')->nullable()->after('journal_id')
                ->constrained('accounts')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_account_id');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->decimal('tds_amount', 12, 2)->default(0)->after('net_salary');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropForeign(['paid_account_id']);
            $table->dropColumn(['journal_id', 'paid_account_id', 'paid_at']);
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn('tds_amount');
        });
    }
};
