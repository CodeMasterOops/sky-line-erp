<?php

namespace App\Services\Nepal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the IRD-format VAT register queries for D3 (sales) and D4 (purchase).
 *
 * Controllers are responsible for CSV rendering; this service owns the data.
 * Keeping raw SQL out of controllers makes both sides independently testable.
 */
class VatReportService
{
    /**
     * Approved sales invoices within the date range, grouped per invoice.
     *
     * @return Collection<int, object>
     */
    public function fetchSalesRows(int $companyId, string $startDate, string $endDate): Collection
    {
        return DB::table('invoices')
            ->leftJoin('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoin('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.status', 'approved')
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_items.deleted_at')
            ->whereBetween('invoices.invoice_date', [$startDate, $endDate])
            ->groupBy(
                'invoices.id', 'invoices.invoice_no', 'invoices.invoice_date',
                'parties.pan', 'parties.name'
            )
            ->select([
                'invoices.invoice_no',
                'invoices.invoice_date',
                'parties.pan as party_pan',
                'parties.name as party_name',
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN invoice_items.tax_amount ELSE 0 END) as vat_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'zero_rated' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as zero_rated_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'exempt' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
    }

    /**
     * Approved credit notes (sales returns) within the date range.
     *
     * @return Collection<int, object>
     */
    public function fetchCreditNoteRows(int $companyId, string $startDate, string $endDate): Collection
    {
        return DB::table('credit_notes')
            ->leftJoin('parties', 'parties.id', '=', 'credit_notes.party_id')
            ->leftJoin('credit_note_items', 'credit_note_items.credit_note_id', '=', 'credit_notes.id')
            ->where('credit_notes.company_id', $companyId)
            ->where('credit_notes.status', 'approved')
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereNull('credit_note_items.deleted_at')
            ->whereBetween('credit_notes.credit_note_date', [$startDate, $endDate])
            ->groupBy(
                'credit_notes.id', 'credit_notes.credit_note_no', 'credit_notes.credit_note_date',
                'parties.pan', 'parties.name'
            )
            ->select([
                'credit_notes.credit_note_no',
                'credit_notes.credit_note_date',
                'parties.pan as party_pan',
                'parties.name as party_name',
                DB::raw("SUM(CASE WHEN credit_note_items.tax_line_type = 'taxable' THEN (credit_note_items.quantity * credit_note_items.rate) - credit_note_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN credit_note_items.tax_line_type = 'taxable' THEN credit_note_items.tax_amount ELSE 0 END) as vat_amount"),
                DB::raw("SUM(CASE WHEN credit_note_items.tax_line_type = 'zero_rated' THEN (credit_note_items.quantity * credit_note_items.rate) - credit_note_items.discount_amount ELSE 0 END) as zero_rated_amount"),
                DB::raw("SUM(CASE WHEN credit_note_items.tax_line_type = 'exempt' THEN (credit_note_items.quantity * credit_note_items.rate) - credit_note_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
    }

    /**
     * Approved purchase bills within the date range.
     *
     * @return Collection<int, object>
     */
    public function fetchPurchaseRows(int $companyId, string $startDate, string $endDate): Collection
    {
        return DB::table('bills')
            ->leftJoin('parties', 'parties.id', '=', 'bills.party_id')
            ->leftJoin('bill_items', 'bill_items.bill_id', '=', 'bills.id')
            ->where('bills.company_id', $companyId)
            ->where('bills.status', 'approved')
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereNull('bill_items.deleted_at')
            ->whereBetween('bills.bill_date', [$startDate, $endDate])
            ->groupBy(
                'bills.id', 'bills.bill_no', 'bills.bill_date',
                'parties.pan', 'parties.name'
            )
            ->select([
                'bills.bill_no',
                'bills.bill_date',
                'parties.pan as party_pan',
                'parties.name as party_name',
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'taxable' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'taxable' THEN bill_items.tax_amount ELSE 0 END) as input_vat"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'zero_rated' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as zero_rated_amount"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'exempt' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
    }

    /**
     * Approved debit notes (purchase returns) within the date range.
     *
     * @return Collection<int, object>
     */
    public function fetchDebitNoteRows(int $companyId, string $startDate, string $endDate): Collection
    {
        return DB::table('debit_notes')
            ->leftJoin('parties', 'parties.id', '=', 'debit_notes.party_id')
            ->leftJoin('debit_note_items', 'debit_note_items.debit_note_id', '=', 'debit_notes.id')
            ->where('debit_notes.company_id', $companyId)
            ->where('debit_notes.status', 'approved')
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->whereNull('debit_note_items.deleted_at')
            ->whereBetween('debit_notes.debit_note_date', [$startDate, $endDate])
            ->groupBy(
                'debit_notes.id', 'debit_notes.debit_note_no', 'debit_notes.debit_note_date',
                'parties.pan', 'parties.name'
            )
            ->select([
                'debit_notes.debit_note_no',
                'debit_notes.debit_note_date',
                'parties.pan as party_pan',
                'parties.name as party_name',
                DB::raw("SUM(CASE WHEN debit_note_items.tax_line_type = 'taxable' THEN (debit_note_items.quantity * debit_note_items.rate) - debit_note_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN debit_note_items.tax_line_type = 'taxable' THEN debit_note_items.tax_amount ELSE 0 END) as input_vat"),
                DB::raw("SUM(CASE WHEN debit_note_items.tax_line_type = 'zero_rated' THEN (debit_note_items.quantity * debit_note_items.rate) - debit_note_items.discount_amount ELSE 0 END) as zero_rated_amount"),
                DB::raw("SUM(CASE WHEN debit_note_items.tax_line_type = 'exempt' THEN (debit_note_items.quantity * debit_note_items.rate) - debit_note_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
    }
}
