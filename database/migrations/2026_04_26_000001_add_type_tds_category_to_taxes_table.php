<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->string('type')->nullable()->after('rate');
            $table->string('tds_category')->nullable()->after('type');
            $table->boolean('is_system')->default(false)->after('tds_category');
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn(['type', 'tds_category', 'is_system']);
        });
    }
};
