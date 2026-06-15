<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe to re-run: checks index existence before creating (partial-failure recovery).
        $this->addIndexIfMissing('bills', ['company_id', 'status'], 'bills_company_status_idx');
        $this->addIndexIfMissing('bills', ['party_id', 'bill_date'], 'bills_party_date_idx');
        $this->addIndexIfMissing('bills', ['fiscal_year_id', 'status'], 'bills_fiscal_status_idx');
        $this->addIndexIfMissing('bills', ['voided_at'], 'bills_voided_at_idx');
        $this->addIndexIfMissing('purchase_orders', ['company_id', 'status'], 'purchase_orders_company_status_idx');
        $this->addIndexIfMissing('purchase_orders', ['party_id', 'order_date'], 'purchase_orders_party_date_idx');
        $this->addIndexIfMissing('debit_notes', ['company_id', 'status'], 'debit_notes_company_status_idx');
        $this->addIndexIfMissing('debit_notes', ['party_id', 'debit_note_date'], 'debit_notes_party_date_idx');
        $this->addIndexIfMissing('payments', ['company_id', 'status'], 'payments_company_status_idx');
        $this->addIndexIfMissing('payments', ['party_id', 'payment_date'], 'payments_party_date_idx');
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))->pluck('name');
        if ($existing->contains($name)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $name) {
            $table->index($columns, $name);
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndexIfExists('bills_company_status_idx');
            $table->dropIndexIfExists('bills_party_date_idx');
            $table->dropIndexIfExists('bills_fiscal_status_idx');
            $table->dropIndexIfExists('bills_voided_at_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndexIfExists('purchase_orders_company_status_idx');
            $table->dropIndexIfExists('purchase_orders_party_date_idx');
        });

        Schema::table('debit_notes', function (Blueprint $table) {
            $table->dropIndexIfExists('debit_notes_company_status_idx');
            $table->dropIndexIfExists('debit_notes_party_date_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndexIfExists('payments_company_status_idx');
            $table->dropIndexIfExists('payments_party_date_idx');
        });
    }
};
