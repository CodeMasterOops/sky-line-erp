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
        Schema::table('parties', function (Blueprint $table) {
            $table->string('payment_terms', 20)->default('net30')->after('credit_limit');
            $table->smallInteger('credit_days')->default(30)->after('payment_terms');
            $table->smallInteger('custom_days')->nullable()->after('credit_days');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'credit_days', 'custom_days']);
        });
    }
};
