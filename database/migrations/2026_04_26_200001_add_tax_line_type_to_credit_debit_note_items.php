<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->string('tax_line_type')->default('taxable')->after('tax_amount');
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->string('tax_line_type')->default('taxable')->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropColumn('tax_line_type');
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->dropColumn('tax_line_type');
        });
    }
};
