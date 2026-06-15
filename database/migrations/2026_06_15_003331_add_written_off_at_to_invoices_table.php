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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('written_off_at')->nullable()->after('voided_at');
            $table->decimal('write_off_amount', 15, 4)->nullable()->after('written_off_at');
            $table->text('write_off_remarks')->nullable()->after('write_off_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['written_off_at', 'write_off_amount', 'write_off_remarks']);
        });
    }
};
