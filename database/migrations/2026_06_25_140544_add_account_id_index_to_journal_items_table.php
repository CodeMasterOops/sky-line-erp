<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Add a composite index on (account_id, deleted_at) for journal_items.
 *
 * Every GL account balance query — Trial Balance, Balance Sheet, P&L, Account
 * Ledger — filters journal_items by account_id. Without this index MySQL/PG
 * performs a full-table scan on journal_items for every account on every
 * report, which becomes the primary bottleneck at period-end as the table grows.
 *
 * Including deleted_at in the index lets the common `whereNull('deleted_at')`
 * soft-delete filter use the same index (partial-index semantics without
 * requiring a DB partial-index feature).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            if (! $this->indexExists('journal_items', 'journal_items_account_id_deleted_at_index')) {
                $table->index(['account_id', 'deleted_at'], 'journal_items_account_id_deleted_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('journal_items_account_id_deleted_at_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($indexName);
    }
};
