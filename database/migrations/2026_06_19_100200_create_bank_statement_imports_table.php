<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('file_name')->nullable();
            $table->string('file_hash', 40)->nullable();
            $table->string('source')->default('csv'); // csv | xlsx | manual | api
            $table->json('column_map')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->string('status')->default('completed'); // pending | completed | failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_imports');
    }
};
