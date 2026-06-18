<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bom_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bom_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('work_center')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('bom_id')->references('id')->on('boms')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['bom_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_operations');
    }
};
