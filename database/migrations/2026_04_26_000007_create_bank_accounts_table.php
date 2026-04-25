<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('branch')->nullable();
            $table->string('currency')->default('NPR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
