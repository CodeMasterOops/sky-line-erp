<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('product_categories')
                ->nullOnDelete();

            $table->unique(['company_id', 'parent_id', 'name'], 'product_categories_company_parent_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique('product_categories_company_parent_name_unique');
            $table->dropForeign(['parent_id']);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();
        });
    }
};
