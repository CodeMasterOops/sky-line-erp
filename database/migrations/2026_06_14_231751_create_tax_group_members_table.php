<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->constrained('taxes')->noActionOnDelete();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
            $table->unique(['tax_group_id', 'tax_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_group_members');
    }
};
