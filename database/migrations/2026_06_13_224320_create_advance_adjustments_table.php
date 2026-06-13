<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advance_receipt_id')->constrained('advance_receipts')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->decimal('amount', 12, 2);
            $table->timestamp('adjusted_at')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals');
            $table->foreignId('create_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_adjustments');
    }
};
