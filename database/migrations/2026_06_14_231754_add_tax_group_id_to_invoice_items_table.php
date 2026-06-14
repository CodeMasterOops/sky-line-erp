<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'tax_group_id')) {
                $table->foreignId('tax_group_id')->nullable()->after('tax_id')
                    ->constrained('tax_groups')->nullOnDelete();
            } else {
                // Column exists (from a partial prior run) — just add the missing FK constraint.
                $table->foreignId('tax_group_id')->nullable()->change();
                $table->foreign('tax_group_id')->references('id')->on('tax_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_group_id');
        });
    }
};
