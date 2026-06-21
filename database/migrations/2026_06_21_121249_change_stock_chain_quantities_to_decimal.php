<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Move the inventory quantity chain from integer to decimal(15,4) so fractional
 * units (e.g. 1.5 kg, 0.25 L) flow through stock, layers, movements and the
 * documents that drive them without being truncated. Costs were already decimal;
 * batch quantities (decimal 15,4) and bill/debit-note item quantities were too,
 * so this aligns the rest of the chain with them.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> table => quantity column
     */
    private array $columns = [
        'stocks' => 'quantity',
        'stock_layers' => 'qty_remaining',
        'stock_movements' => 'quantity',
        'stock_movement_layers' => 'quantity',
        'stock_reservations' => 'quantity',
        'damage_report_items' => 'quantity',
        'stock_adjustment_items' => 'quantity',
        'opening_stock_entry_items' => 'quantity',
        'stock_transfer_items' => 'quantity',
        'invoice_items' => 'quantity',
        'credit_note_items' => 'quantity',
        'inventory_valuation_snapshots' => 'qty_remaining',
    ];

    public function up(): void
    {
        // stocks.on_hold is a second quantity column on the stocks table.
        Schema::table('stocks', function (Blueprint $table): void {
            $table->decimal('on_hold', 15, 4)->default(0)->change();
        });

        foreach ($this->columns as $tableName => $column) {
            // stock_movements / stock_movement_layers are NOT NULL with no default;
            // every other column defaults to 0.
            $hasDefault = ! in_array($tableName, ['stock_movements', 'stock_movement_layers'], true);

            Schema::table($tableName, function (Blueprint $table) use ($column, $hasDefault): void {
                $definition = $table->decimal($column, 15, 4);
                if ($hasDefault) {
                    $definition->default(0);
                }
                $definition->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table): void {
            $table->integer('on_hold')->default(0)->change();
        });

        foreach ($this->columns as $tableName => $column) {
            $hasDefault = ! in_array($tableName, ['stock_movements', 'stock_movement_layers'], true);

            Schema::table($tableName, function (Blueprint $table) use ($column, $hasDefault): void {
                $definition = $table->integer($column);
                if ($hasDefault) {
                    $definition->default(0);
                }
                $definition->change();
            });
        }
    }
};
