<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('low_stock_alert')->default(true);
            $table->boolean('invoice_due_reminder')->default(true);
            $table->json('invoice_due_reminder_days')->nullable();
            $table->boolean('bill_due_reminder')->default(true);
            $table->json('bill_due_reminder_days')->nullable();
            $table->boolean('payroll_processed_alert')->default(true);
            $table->boolean('leave_approval_alert')->default(true);
            $table->boolean('stock_expiry_alert')->default(true);
            $table->unsignedSmallInteger('stock_expiry_days')->default(30);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('in_app_notifications')->default(true);
            $table->timestamps();
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_notification_settings');
    }
};
