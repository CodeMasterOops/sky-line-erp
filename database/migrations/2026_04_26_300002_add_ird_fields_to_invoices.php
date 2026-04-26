<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds IRD (Inland Revenue Department) e-invoicing fields to invoices.
 *
 * ird_sync_status: 'pending' | 'synced' | 'failed' | 'skipped'
 * ird_internal_id: IRD-assigned document ID after successful sync
 * ird_qr_data:     QR code content provided by IRD (base64 or URL)
 * ird_synced_at:   Timestamp of last successful sync
 * ird_error:       Last error message from IRD API
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('ird_sync_status', 20)->default('pending')->after('voided_at');
            $table->string('ird_internal_id')->nullable()->after('ird_sync_status');
            $table->text('ird_qr_data')->nullable()->after('ird_internal_id');
            $table->timestamp('ird_synced_at')->nullable()->after('ird_qr_data');
            $table->text('ird_error')->nullable()->after('ird_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['ird_sync_status', 'ird_internal_id', 'ird_qr_data', 'ird_synced_at', 'ird_error']);
        });
    }
};
