<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            // Must drop the foreign key constraint before the unique index
            $table->dropForeign(['company_id']);
            $table->dropUnique(['company_id', 'code']);
            $table->dropColumn('company_id');
            // Add a global unique constraint on code
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->unique(['company_id', 'code']);
        });
    }
};
