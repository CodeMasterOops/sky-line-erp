<?php

use App\Enums\StatusEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
            $table->foreignId('party_id')->constrained('parties');
            $table->string('advance_no', 50);
            $table->date('advance_date');
            $table->string('payment_method', 50)->default(PaymentMethodEnum::Cash->value);
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('amount', 12, 2);
            $table->decimal('adjusted_amount', 12, 2)->default(0);
            $table->string('reference_no', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default(StatusEnum::DRAFT->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_receipts');
    }
};
