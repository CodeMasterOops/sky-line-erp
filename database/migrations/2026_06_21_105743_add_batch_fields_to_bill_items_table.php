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
        Schema::table('bill_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bill_items', 'batch_no')) {
                $table->string('batch_no')->nullable()->after('batch_id');
            }
            if (! Schema::hasColumn('bill_items', 'mfg_date')) {
                $table->date('mfg_date')->nullable()->after('batch_no');
            }
            if (! Schema::hasColumn('bill_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('mfg_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['batch_no', 'mfg_date', 'expiry_date']);
        });
    }
};
