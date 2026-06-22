<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('grn_items', 'batch_id')) {
            return;
        }

        Schema::table('grn_items', function (Blueprint $table) {
            $table->unsignedBigInteger('batch_id')->nullable()->after('expiry_date');
            $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            if (Schema::hasColumn('grn_items', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
    }
};
