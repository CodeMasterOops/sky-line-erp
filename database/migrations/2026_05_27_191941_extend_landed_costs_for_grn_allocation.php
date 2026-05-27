<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            $table->string('treatment', 32)->default('capitalized')->after('description');
            $table->string('allocation_method', 32)->default('value')->after('treatment');
            $table->decimal('vat_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('vat_claimable_amount', 15, 2)->default(0)->after('vat_amount');
        });

        Schema::table('stock_layers', function (Blueprint $table) {
            $table->decimal('base_unit_cost', 16, 4)->default(0)->after('unit_cost');
            $table->decimal('landed_unit_cost', 16, 4)->default(0)->after('base_unit_cost');
        });

        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landed_cost_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grn_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_layer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('allocated_unit_cost', 16, 4)->default(0);
            $table->timestamps();

            $table->index(['landed_cost_id', 'grn_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');

        Schema::table('stock_layers', function (Blueprint $table) {
            $table->dropColumn(['base_unit_cost', 'landed_unit_cost']);
        });

        Schema::table('landed_costs', function (Blueprint $table) {
            $table->dropColumn([
                'treatment',
                'allocation_method',
                'vat_amount',
                'vat_claimable_amount',
            ]);
        });
    }
};
