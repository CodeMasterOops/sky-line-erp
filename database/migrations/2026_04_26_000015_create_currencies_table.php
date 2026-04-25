<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->string('code', 3);
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->decimal('exchange_rate', 15, 6)->default(1);
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('rate_date')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('NPR')->after('remarks');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency_code');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('NPR')->after('tds_amount');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
        Schema::dropIfExists('currencies');
    }
};
