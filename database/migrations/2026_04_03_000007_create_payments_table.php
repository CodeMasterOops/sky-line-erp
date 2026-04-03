<?php

use App\Enums\StatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_no');
            $table->date('payment_date');
            $table->string('payment_method');
            $table->foreignId('account_id')->constrained()->noActionOnDelete();
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', [StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])->default(StatusEnum::DRAFT->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
