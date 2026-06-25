<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->foreignId('tax_group_id')->nullable()->after('tax_id')->constrained('tax_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TaxGroup::class);
            $table->dropColumn('tax_group_id');
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TaxGroup::class);
            $table->dropColumn('tax_group_id');
        });
    }
};
