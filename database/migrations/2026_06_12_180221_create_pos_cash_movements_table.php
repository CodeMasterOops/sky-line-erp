<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained('pos_sessions')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20); // cash_in, cash_out
            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['pos_session_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
    }
};
