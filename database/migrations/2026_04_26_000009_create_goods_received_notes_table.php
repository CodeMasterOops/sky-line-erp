<?php

use App\Enums\StatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('grn_no');
            $table->date('received_date');
            $table->string('supplier_invoice_no')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', [StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])->default(StatusEnum::DRAFT->value);
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_received_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('ordered_qty', 12, 2)->default(0);
            $table->decimal('received_qty', 12, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
        Schema::dropIfExists('goods_received_notes');
    }
};
