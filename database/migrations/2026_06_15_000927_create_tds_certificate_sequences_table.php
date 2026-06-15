<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_certificate_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('fiscal_year_code', 10);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_certificate_sequences');
    }
};
