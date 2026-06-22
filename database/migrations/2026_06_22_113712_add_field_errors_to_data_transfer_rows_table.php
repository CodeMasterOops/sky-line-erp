<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_transfer_rows', function (Blueprint $table) {
            $table->json('field_errors')->nullable()->after('errors');
        });
    }

    public function down(): void
    {
        Schema::table('data_transfer_rows', function (Blueprint $table) {
            $table->dropColumn('field_errors');
        });
    }
};
