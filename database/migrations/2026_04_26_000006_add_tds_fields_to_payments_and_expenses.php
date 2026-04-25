<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('tds_category')->nullable()->after('remarks');
            $table->decimal('tds_rate', 6, 2)->nullable()->after('tds_category');
            $table->decimal('tds_amount', 15, 2)->default(0)->after('tds_rate');
            $table->decimal('gross_amount', 15, 2)->default(0)->after('tds_amount');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('tds_category')->nullable()->after('remarks');
            $table->decimal('tds_rate', 6, 2)->nullable()->after('tds_category');
            $table->decimal('tds_amount', 15, 2)->default(0)->after('tds_rate');
            $table->decimal('gross_amount', 15, 2)->default(0)->after('tds_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['tds_category', 'tds_rate', 'tds_amount', 'gross_amount']);
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['tds_category', 'tds_rate', 'tds_amount', 'gross_amount']);
        });
    }
};
