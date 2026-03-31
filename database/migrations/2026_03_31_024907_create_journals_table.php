<?php

use App\Enums\StatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->nullableMorphs('reference');
            $table->string('reference_no')->nullable();
            $table->date('date');
            $table->text('remarks')->nullable();
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', [StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])->default(StatusEnum::DRAFT->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
