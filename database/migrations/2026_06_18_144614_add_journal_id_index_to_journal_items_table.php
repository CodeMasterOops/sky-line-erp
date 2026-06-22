<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            // SQLite does not auto-create indexes on FK columns; explicit index
            // speeds up all report joins on journal_items.journal_id.
            if (! $this->indexExists('journal_items', 'journal_items_journal_id_index')) {
                $table->index('journal_id', 'journal_items_journal_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journal_items', function (Blueprint $table) {
            $table->dropIndex('journal_items_journal_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(\Illuminate\Support\Facades\Schema::getIndexes($table))
            ->pluck('name')
            ->contains($indexName);
    }
};
