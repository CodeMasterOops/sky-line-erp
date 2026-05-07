<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $tables = [
        'quotations',
        'sales_orders',
        'purchase_orders',
        'credit_notes',
        'debit_notes',
        'goods_received_notes',
        'delivery_challans',
        'stock_adjustments',
        'pos_held_orders',
        'stocks',
        'stock_movements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
