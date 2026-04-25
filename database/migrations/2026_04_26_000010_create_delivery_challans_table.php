<?php

use App\Enums\StatusEnum;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->noActionOnDelete();
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('reference');
            $table->string('challan_no');
            $table->date('challan_date');
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('delivery_address')->nullable();
            $table->text('remarks')->nullable();
            $table->string('receiver_name')->nullable();
            $table->enum('status', [StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])->default(StatusEnum::DRAFT->value);
            $table->foreignId('create_user_id')->constrained('users');
            $table->foreignId('approve_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('delivery_challan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_challan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_challan_items');
        Schema::dropIfExists('delivery_challans');
    }
};
