<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->string('type', 30);
            $table->string('name');
            $table->string('code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('pan')->nullable();
            $table->string('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
