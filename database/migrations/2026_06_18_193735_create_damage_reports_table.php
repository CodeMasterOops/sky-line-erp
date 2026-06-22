<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('reference_no')->nullable();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_reports');
    }
};
