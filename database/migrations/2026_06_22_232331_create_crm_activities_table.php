<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('subject');
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'crm_activities_subject_occurred_idx');
            $table->index(['branch_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
