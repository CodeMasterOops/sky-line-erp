<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('ward_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->string('postal_code', 20)->nullable()->after('ward_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['ward_id']);
            $table->dropColumn(['ward_id', 'postal_code']);
        });
    }
};
