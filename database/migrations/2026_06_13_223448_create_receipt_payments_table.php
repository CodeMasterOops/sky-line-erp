<?php

use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method', 50)->default(PaymentMethodEnum::Cash->value);
            $table->foreignId('account_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('reference_no', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('cheque_status', 20)->nullable()->default(null);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_payments');
    }
};
