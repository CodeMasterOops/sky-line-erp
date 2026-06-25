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
        Schema::table('tax_groups', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->boolean('is_default')->default(false)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('tax_groups', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_default']);
        });
    }
};
