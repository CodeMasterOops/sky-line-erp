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
        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('batch_no')->constrained('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serial_numbers', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
