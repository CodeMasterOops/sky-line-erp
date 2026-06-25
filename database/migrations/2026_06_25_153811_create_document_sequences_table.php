<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('prefix')->default('');
            $table->unsignedTinyInteger('padding')->default(5);
            $table->string('separator')->default('/');
            $table->boolean('reset_yearly')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'document_type']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
