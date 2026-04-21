<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('qty_remaining')->default(0);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->timestamp('received_at');
            $table->string('lot_number')->nullable();
            $table->foreignId('source_bill_item_id')->nullable()->constrained('bill_items')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_variant_id', 'warehouse_id', 'received_at']);
            $table->index(['company_id', 'product_variant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layers');
    }
};
