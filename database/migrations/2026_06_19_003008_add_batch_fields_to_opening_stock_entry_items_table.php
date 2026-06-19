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
        Schema::table('opening_stock_entry_items', function (Blueprint $table) {
            $table->string('batch_no', 100)->nullable()->after('batch_id');
            $table->date('expiry_date')->nullable()->after('batch_no');
        });
    }

    public function down(): void
    {
        Schema::table('opening_stock_entry_items', function (Blueprint $table) {
            $table->dropColumn(['batch_no', 'expiry_date']);
        });
    }
};
