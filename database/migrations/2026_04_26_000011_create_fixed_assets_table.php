<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->string('name');
            $table->string('depreciation_method')->default('slm');
            $table->decimal('useful_life_years', 5, 1)->default(5);
            $table->decimal('salvage_value_percent', 5, 2)->default(0);
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable();
            $table->foreign('accumulated_depreciation_account_id', 'fac_accum_dep_account_fk')
                ->references('id')->on('accounts')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fixed_asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_code');
            $table->string('name');
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->decimal('useful_life_years', 5, 1)->default(5);
            $table->string('depreciation_method')->default('slm');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->date('last_depreciation_date')->nullable();
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->string('status')->default('active');
            $table->foreignId('asset_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable();
            $table->foreign('accumulated_depreciation_account_id', 'fa_accum_dep_account_fk')
                ->references('id')->on('accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
