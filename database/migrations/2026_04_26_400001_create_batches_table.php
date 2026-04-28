<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('batch_no', 100);
            $table->string('lot_no', 100)->nullable();
            $table->date('mfg_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('initial_qty', 15, 4)->default(0);
            $table->decimal('remaining_qty', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->string('status', 20)->default('active'); // active, expired, depleted
            $table->text('remarks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'product_variant_id', 'expiry_date']);
            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'product_variant_id', 'warehouse_id', 'batch_no'], 'batches_unique');
        });

        // Link batches to GRN items
        Schema::table('grn_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_id')->constrained('batches')->nullOnDelete();
        });

        // Link batches to invoice items (outward allocation)
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_id')->constrained('batches')->nullOnDelete();
        });

        // Link batches to bill items
        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('unit_id')->constrained('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', fn (Blueprint $t) => $t->dropForeign(['batch_id']));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropForeign(['batch_id']));
        Schema::table('grn_items', fn (Blueprint $t) => $t->dropForeign(['batch_id']));
        Schema::dropIfExists('batches');
    }
};
