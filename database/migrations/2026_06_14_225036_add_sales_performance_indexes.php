<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['company_id', 'party_id', 'status'], 'invoices_company_party_status_idx');
            $table->index(['company_id', 'fiscal_year_id', 'status'], 'invoices_company_fy_status_idx');
            $table->index(['company_id', 'invoice_date'], 'invoices_company_date_idx');
            $table->index('voided_at', 'invoices_voided_at_idx');
        });

        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->index('invoice_id', 'receipt_allocations_invoice_id_idx');
            $table->index('receipt_id', 'receipt_allocations_receipt_id_idx');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->index('invoice_id', 'invoice_items_invoice_id_idx');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->index('invoice_id', 'credit_notes_invoice_id_idx');
        });

        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->index(['company_id', 'deductible_type', 'deductible_id'], 'tds_deductions_company_deductible_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropIndex('tds_deductions_company_deductible_idx');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropIndex('credit_notes_invoice_id_idx');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndex('invoice_items_invoice_id_idx');
        });

        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->dropIndex('receipt_allocations_receipt_id_idx');
            $table->dropIndex('receipt_allocations_invoice_id_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_voided_at_idx');
            $table->dropIndex('invoices_company_date_idx');
            $table->dropIndex('invoices_company_fy_status_idx');
            $table->dropIndex('invoices_company_party_status_idx');
        });
    }
};
