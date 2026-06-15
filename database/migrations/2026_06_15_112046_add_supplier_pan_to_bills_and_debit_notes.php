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
        Schema::table('bills', function (Blueprint $table) {
            $table->string('supplier_pan')->nullable()->after('party_id');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->string('supplier_pan')->nullable()->after('party_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('supplier_pan');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropColumn('supplier_pan');
        });
    }
};
