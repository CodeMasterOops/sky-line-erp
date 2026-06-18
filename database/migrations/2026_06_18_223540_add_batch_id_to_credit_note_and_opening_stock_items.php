<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('discount_amount')->constrained('batches')->nullOnDelete();
        });

        Schema::table('opening_stock_entry_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_cost')->constrained('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('opening_stock_entry_items', fn (Blueprint $t) => $t->dropForeign(['batch_id']));
        Schema::table('credit_note_items', fn (Blueprint $t) => $t->dropForeign(['batch_id']));
    }
};
