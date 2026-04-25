<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serial_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('serial_no');
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('in_stock');
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('receipt_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('issue_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'product_variant_id', 'serial_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serial_numbers');
    }
};
