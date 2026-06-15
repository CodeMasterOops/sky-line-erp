<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // SCH-001 / BRANCH-001: stock_transfers missing branch_id
        if (Schema::hasTable('stock_transfers') && ! Schema::hasColumn('stock_transfers', 'branch_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }

        // BUG-004: opening_stock_entry_items missing company_id (MultiTenant trait is a no-op without it)
        if (Schema::hasTable('opening_stock_entry_items') && ! Schema::hasColumn('opening_stock_entry_items', 'company_id')) {
            Schema::table('opening_stock_entry_items', function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });

            // Backfill company_id from the parent opening_stock_entry
            DB::statement('
                UPDATE opening_stock_entry_items
                SET company_id = (
                    SELECT company_id FROM opening_stock_entries
                    WHERE opening_stock_entries.id = opening_stock_entry_items.opening_stock_entry_id
                )
                WHERE company_id IS NULL
            ');
        }

        // BUG-005: stock_transfer_items missing company_id
        if (Schema::hasTable('stock_transfer_items') && ! Schema::hasColumn('stock_transfer_items', 'company_id')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });

            DB::statement('
                UPDATE stock_transfer_items
                SET company_id = (
                    SELECT company_id FROM stock_transfers
                    WHERE stock_transfers.id = stock_transfer_items.stock_transfer_id
                )
                WHERE company_id IS NULL
            ');
        }

        // SCH-005: serial_numbers missing soft deletes
        if (Schema::hasTable('serial_numbers') && ! Schema::hasColumn('serial_numbers', 'deleted_at')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // PERF-002 / SCH-002: stock_movements missing composite index for ledger queries
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->index(
                    ['company_id', 'product_variant_id', 'warehouse_id'],
                    'stock_movements_company_variant_warehouse_idx'
                );
            });
        }

        // SCH-002: stock_layers — add FIFO-optimised covering index
        if (Schema::hasTable('stock_layers')) {
            Schema::table('stock_layers', function (Blueprint $table) {
                $table->index(
                    ['company_id', 'product_variant_id', 'warehouse_id', 'received_at', 'id'],
                    'stock_layers_fifo_covering_idx'
                );
            });
        }

        // SCH-004: serial_numbers — add status lookup index
        if (Schema::hasTable('serial_numbers')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $table->index(['company_id', 'status'], 'serial_numbers_company_status_idx');
            });
        }

        // SCH-003: product_variants — add SKU lookup index
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->index(['company_id', 'sku'], 'product_variants_company_sku_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_transfers') && Schema::hasColumn('stock_transfers', 'branch_id')) {
            Schema::table('stock_transfers', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasTable('opening_stock_entry_items') && Schema::hasColumn('opening_stock_entry_items', 'company_id')) {
            Schema::table('opening_stock_entry_items', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasTable('stock_transfer_items') && Schema::hasColumn('stock_transfer_items', 'company_id')) {
            Schema::table('stock_transfer_items', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasTable('serial_numbers') && Schema::hasColumn('serial_numbers', 'deleted_at')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_company_variant_warehouse_idx');
        });

        Schema::table('stock_layers', function (Blueprint $table) {
            $table->dropIndex('stock_layers_fifo_covering_idx');
        });

        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->dropIndex('serial_numbers_company_status_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_company_sku_idx');
        });
    }
};
