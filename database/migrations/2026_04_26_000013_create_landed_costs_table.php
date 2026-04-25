<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->string('cost_type');
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_costs');
    }
};
