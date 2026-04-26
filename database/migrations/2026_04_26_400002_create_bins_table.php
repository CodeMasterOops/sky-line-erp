<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 50)->nullable();
            $table->string('zone', 50)->nullable();  // A, B, C zone / aisle
            $table->string('rack', 50)->nullable();
            $table->string('level', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'warehouse_id']);
            $table->unique(['company_id', 'warehouse_id', 'code'], 'bins_code_unique');
        });

        // Add bin to stocks (per bin stock balance)
        Schema::table('stocks', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->after('warehouse_id')->constrained('bins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', fn (Blueprint $t) => $t->dropForeign(['bin_id']));
        Schema::dropIfExists('bins');
    }
};
