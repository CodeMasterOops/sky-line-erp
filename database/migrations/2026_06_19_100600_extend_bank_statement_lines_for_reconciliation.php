<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->foreignId('import_id')->nullable()->after('bank_account_id')
                ->constrained('bank_statement_imports')->nullOnDelete();
            $table->foreignId('reconciliation_id')->nullable()->after('journal_item_id')
                ->constrained('bank_reconciliations')->nullOnDelete();
            $table->string('external_ref')->nullable()->after('reference');
            $table->string('match_type')->nullable()->after('status'); // auto | manual | rule | created
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->dropForeign(['import_id']);
            $table->dropForeign(['reconciliation_id']);
            $table->dropColumn(['import_id', 'reconciliation_id', 'external_ref', 'match_type']);
        });
    }
};
