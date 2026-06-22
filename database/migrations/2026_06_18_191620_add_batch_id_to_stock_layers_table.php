<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('lot_number');
            $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
