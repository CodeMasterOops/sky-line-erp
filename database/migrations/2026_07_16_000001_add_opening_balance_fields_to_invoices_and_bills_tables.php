<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_opening')->default(false)->after('status');
            $table->decimal('opening_amount', 14, 2)->default(0)->after('is_opening');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->boolean('is_opening')->default(false)->after('status');
            $table->decimal('opening_amount', 14, 2)->default(0)->after('is_opening');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['is_opening', 'opening_amount']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['is_opening', 'opening_amount']);
        });
    }
};
