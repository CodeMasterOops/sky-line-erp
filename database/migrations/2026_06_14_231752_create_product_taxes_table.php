<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('taxes')->noActionOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_taxes');
    }
};
