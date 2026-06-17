<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_consumptions', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        // Backfill company_id from the parent production_order via correlated subquery (SQLite-safe)
        DB::statement('
            UPDATE production_order_consumptions
            SET company_id = (
                SELECT po.company_id
                FROM production_orders po
                WHERE po.id = production_order_consumptions.production_order_id
            )
        ');
    }

    public function down(): void
    {
        Schema::table('production_order_consumptions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
