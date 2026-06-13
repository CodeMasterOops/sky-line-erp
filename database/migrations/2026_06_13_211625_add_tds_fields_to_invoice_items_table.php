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
            $table->boolean('is_tds_applicable')->default(false)->after('tax_line_type');
            $table->foreignId('tds_id')->nullable()->after('is_tds_applicable')->constrained('taxes')->nullOnDelete();
            $table->decimal('tds_base_amount', 12, 2)->default(0)->after('tds_id');
            $table->decimal('tds_amount', 12, 2)->default(0)->after('tds_base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['tds_id']);
            $table->dropColumn(['is_tds_applicable', 'tds_id', 'tds_base_amount', 'tds_amount']);
        });
    }
};
