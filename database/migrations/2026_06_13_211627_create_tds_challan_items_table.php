<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tds_challan_id')->constrained('tds_challans')->cascadeOnDelete();
            $table->foreignId('tds_deduction_id')->constrained('tds_deductions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_challan_items');
    }
};
