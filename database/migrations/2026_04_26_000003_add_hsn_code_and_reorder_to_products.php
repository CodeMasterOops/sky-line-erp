<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code')->nullable()->after('code');
            $table->decimal('min_stock_level', 12, 2)->default(0)->after('reorder_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'min_stock_level']);
        });
    }
};
