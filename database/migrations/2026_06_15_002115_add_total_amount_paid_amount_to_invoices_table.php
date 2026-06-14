<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('total_amount', 15, 4)->nullable()->after('due_date');
            $table->decimal('paid_amount', 15, 4)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['total_amount', 'paid_amount']);
        });
    }
};
