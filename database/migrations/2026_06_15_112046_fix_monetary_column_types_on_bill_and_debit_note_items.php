<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Fix bill_items: tax_amount and discount_amount used double; must use decimal for financial precision.
        Schema::table('bill_items', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->change();
            $table->decimal('discount_amount', 12, 2)->default(0)->change();
            // Also fix quantity to decimal to support fractional GRN quantities.
            $table->decimal('quantity', 12, 2)->default(0)->change();
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->change();
            $table->decimal('discount_amount', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->double('tax_amount', 12, 2)->default(0)->change();
            $table->double('discount_amount', 12, 2)->default(0)->change();
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('debit_note_items', function (Blueprint $table) {
            $table->double('tax_amount', 12, 2)->default(0)->change();
            $table->double('discount_amount', 12, 2)->default(0)->change();
        });
    }
};
