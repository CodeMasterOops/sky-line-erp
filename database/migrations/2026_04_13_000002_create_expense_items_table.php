<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->noActionOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained();
            $table->double('tax_amount', 12, 2)->default(0);
            $table->double('discount_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
