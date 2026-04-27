<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->morphs('discountable');
            $table->unique(['discountable_type', 'discountable_id']);
            $table->string('type', 16)->default('fixed');
            $table->decimal('value', 15, 4)->nullable();
            $table->decimal('amount', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
