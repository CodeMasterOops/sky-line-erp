<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Changes tax_amount (and discount_amount where applicable) from double to decimal(15,4)
 * across all line-item tables to prevent floating-point rounding errors on monetary values.
 */
return new class extends Migration
{
    private array $tables = [
        'invoice_items',
        'bill_items',
        'credit_note_items',
        'debit_note_items',
        'sales_order_items',
        'purchase_order_items',
        'quotation_items',
        'expense_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('tax_amount', 15, 4)->default(0)->change();
                $blueprint->decimal('discount_amount', 15, 4)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->double('tax_amount', 12, 2)->default(0)->change();
                $blueprint->double('discount_amount', 12, 2)->default(0)->change();
            });
        }
    }
};
