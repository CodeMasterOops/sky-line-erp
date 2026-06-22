<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_matching_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->string('match_field')->default('description'); // description | reference
            $table->string('operator')->default('contains'); // contains | regex | equals
            $table->string('pattern');
            $table->foreignId('target_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('set_status')->default('matched'); // status applied when rule fires
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id', 'is_active', 'priority'], 'bmr_company_account_active_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_matching_rules');
    }
};
