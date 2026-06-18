<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->morphs('reservable');
            $table->unsignedInteger('quantity');
            $table->timestamp('reserved_at');
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('reserved_by');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('reserved_by')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['company_id', 'product_variant_id', 'warehouse_id', 'released_at'], 'sr_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
