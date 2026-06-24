<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->string('name')->default('Default');
            $table->time('start_time')->default('10:00');
            $table->time('end_time')->default('17:00');
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->decimal('standard_hours_per_day', 4, 2)->default(8);
            $table->decimal('overtime_multiplier', 4, 2)->default(1.5);
            $table->boolean('overtime_enabled')->default(false);
            $table->json('weekly_off_days')->nullable();
            $table->boolean('is_default')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
