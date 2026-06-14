<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('tds_category')->nullable()->after('reference_no');
            $table->decimal('tds_rate', 8, 4)->nullable()->after('tds_category');
            $table->decimal('tds_amount', 15, 4)->nullable()->after('tds_rate');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['tds_category', 'tds_rate', 'tds_amount']);
        });
    }
};
