<?php

use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->string('hash', 40)->nullable()->after('status');
        });

        BankStatementLine::withoutGlobalScopes()
            ->whereNull('hash')
            ->cursor()
            ->each(function (BankStatementLine $line): void {
                $line->forceFill([
                    'hash' => BankStatementLine::makeHash(
                        $line->bank_account_id,
                        $line->transaction_date,
                        $line->debit,
                        $line->credit,
                        $line->reference,
                        $line->balance,
                    ),
                ])->saveQuietly();
            });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->unique(['bank_account_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            $table->dropUnique(['bank_account_id', 'hash']);
            $table->dropColumn('hash');
        });
    }
};
