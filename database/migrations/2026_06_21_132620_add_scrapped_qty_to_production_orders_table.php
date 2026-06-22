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
        Schema::table('production_orders', function (Blueprint $table) {
            // Cumulative defective output across completions. Scrapped units consume materials
            // and conversion but are not added to good finished-goods stock — their cost is
            // expensed to the manufacturing variance account rather than capitalised.
            $table->decimal('scrapped_qty', 15, 4)->default(0)->after('produced_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('scrapped_qty');
        });
    }
};
