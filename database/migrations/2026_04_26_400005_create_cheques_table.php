<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('cheque_no', 50);
            $table->string('bank_name', 150)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->date('cheque_date');           // date on cheque
            $table->date('deposit_date')->nullable(); // date deposited/presented
            $table->date('cleared_date')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('type', 20);  // payable (PDC we issue) | receivable (PDC we receive)
            $table->string('status', 20)->default('pending'); // pending, presented, cleared, bounced, cancelled
            $table->string('reference_type', 100)->nullable(); // App\Models\Invoice | Bill | Receipt | Payment
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('gl_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->foreignId('create_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'status', 'cheque_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
