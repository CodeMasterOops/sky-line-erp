<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_valuation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->date('snapshot_date');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedInteger('qty_remaining')->default(0);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->decimal('total_value', 20, 4)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'snapshot_date']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation_snapshots');
    }
};
