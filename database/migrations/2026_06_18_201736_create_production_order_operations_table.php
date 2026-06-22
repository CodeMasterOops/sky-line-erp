<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_order_id');
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('bom_operation_id');
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->string('work_center')->nullable();
            $table->string('status')->default('pending'); // pending | in_progress | completed | skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('started_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->foreign('production_order_id')->references('id')->on('production_orders')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('bom_operation_id')->references('id')->on('bom_operations')->cascadeOnDelete();
            $table->foreign('started_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
    }
};
