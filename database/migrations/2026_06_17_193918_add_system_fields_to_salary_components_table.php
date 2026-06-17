<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->string('system_code', 30)->nullable()->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'system_code']);
        });
    }
};
