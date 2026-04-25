<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 8, 2)->default(0);
            $table->string('type')->nullable();
            $table->string('tds_category')->nullable();
            $table->boolean('is_default')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_templates');
    }
};
