<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('worked_hours', 5, 2)->default(0)->after('check_out');
            $table->unsignedSmallInteger('late_minutes')->default(0)->after('worked_hours');
            $table->unsignedSmallInteger('early_leaving_minutes')->default(0)->after('late_minutes');
            $table->decimal('overtime_hours', 5, 2)->default(0)->after('early_leaving_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['worked_hours', 'late_minutes', 'early_leaving_minutes', 'overtime_hours']);
        });
    }
};
