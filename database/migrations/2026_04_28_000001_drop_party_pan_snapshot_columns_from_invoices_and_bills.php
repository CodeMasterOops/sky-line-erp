<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Buyer/seller PAN is stored on parties only; these snapshot columns are unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'buyer_pan')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('buyer_pan');
            });
        }
        if (Schema::hasTable('bills') && Schema::hasColumn('bills', 'seller_pan')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->dropColumn('seller_pan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'buyer_pan')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('buyer_pan')->nullable();
            });
        }
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'seller_pan')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->string('seller_pan')->nullable();
            });
        }
    }
};
