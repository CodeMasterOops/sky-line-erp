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
        Schema::table('products', function (Blueprint $table) {
            // Advisory manufacturing classification (raw_material, semi_finished, finished_good,
            // consumable, trading_good). Nullable = unspecified. Does NOT change stock/COGS
            // behaviour — that remains driven by product_type.
            $table->string('item_role', 20)->nullable()->after('product_type');
            $table->index(['company_id', 'item_role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'item_role']);
            $table->dropColumn('item_role');
        });
    }
};
