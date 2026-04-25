<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->string('name');
            $table->string('frequency');
            $table->date('next_run_date');
            $table->date('end_date')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recurring_journal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('dr_amount', 15, 2)->default(0);
            $table->decimal('cr_amount', 15, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_items');
        Schema::dropIfExists('recurring_journals');
    }
};
