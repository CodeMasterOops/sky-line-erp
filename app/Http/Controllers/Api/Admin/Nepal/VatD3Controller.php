<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;
use App\Services\Accounting\AccountReportService;

/**
 * VAT D3 Return — generates the IRD-format D3 (sales register / Bikri Kitab) export.
 *
 * D3 covers sales transactions only. Purchase transactions are in D4 (VatD4Controller).
 * IRD accepted format: CSV with prescribed column order.
 */
class VatD3Controller extends Controller
{
    public function __construct(
        private AccountReportService $reportService,
        private NepaliDateService $nepaliDate,
    ) {}

    /**
     * @Permissions("list_invoice", group="invoice", desc="VAT D3 Return Summary")
     */
    public function summary(Request $request)
    {
        $data = $this->reportService->vatReturn($request);

        return response()->json(['data' => $data]);
    }

    /**
     * @Permissions("list_invoice", group="invoice", desc="Export VAT D3 Sales CSV")
     *
     * Exports the VAT D3 sales register (Bikri Kitab) in IRD's prescribed CSV format.
     * Columns: invoice_no, date_bs, buyer PAN, buyer_name,
     *          taxable_amount, vat_amount, zero_rated_amount, exempt_amount, total_amount
     * Credit notes appear as negative rows per IRD requirement.
     */
    public function exportCsv(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $companyId = auth('admin')->user()->company_id;
        $company = auth('admin')->user()->company;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $startBs = '';
        $endBs = '';
        try {
            $sBs = $this->nepaliDate->adToBs($startDate);
            $eBs = $this->nepaliDate->adToBs($endDate);
            $startBs = $this->nepaliDate->formatBs($sBs['year'], $sBs['month'], $sBs['day']);
            $endBs = $this->nepaliDate->formatBs($eBs['year'], $eBs['month'], $eBs['day']);
        } catch (\Throwable) {
        }

        $rows = [];

        $sales = $this->fetchSalesRows($companyId, $startDate, $endDate);
        foreach ($sales as $row) {
            $dateBs = $this->safeAdToBs($row->invoice_date);
            $rows[] = [
                'type' => 'Sales',
                'doc_no' => $row->invoice_no,
                'date_ad' => $row->invoice_date,
                'date_bs' => $dateBs,
                'pan' => $row->party_pan ?? '',
                'party_name' => $row->party_name ?? 'Cash Customer',
                'taxable_amount' => round($row->taxable_amount ?? 0, 2),
                'vat_amount' => round($row->vat_amount ?? 0, 2),
                'zero_rated_amount' => round($row->zero_rated_amount ?? 0, 2),
                'exempt_amount' => round($row->exempt_amount ?? 0, 2),
                'total_amount' => round(($row->taxable_amount ?? 0) + ($row->vat_amount ?? 0) + ($row->zero_rated_amount ?? 0) + ($row->exempt_amount ?? 0), 2),
            ];
        }

        // IRD D3 requires credit notes (sales returns) as negative rows.
        $creditNotes = $this->fetchCreditNoteRows($companyId, $startDate, $endDate);
        foreach ($creditNotes as $row) {
            $dateBs = $this->safeAdToBs($row->credit_note_date);
            $rows[] = [
                'type' => 'Credit Note',
                'doc_no' => $row->credit_note_no,
                'date_ad' => $row->credit_note_date,
                'date_bs' => $dateBs,
                'pan' => $row->party_pan ?? '',
                'party_name' => $row->party_name ?? '',
                'taxable_amount' => -round($row->taxable_amount ?? 0, 2),
                'vat_amount' => -round($row->vat_amount ?? 0, 2),
                'zero_rated_amount' => -round($row->zero_rated_amount ?? 0, 2),
                'exempt_amount' => -round($row->exempt_amount ?? 0, 2),
                'total_amount' => -round(($row->taxable_amount ?? 0) + ($row->vat_amount ?? 0) + ($row->zero_rated_amount ?? 0) + ($row->exempt_amount ?? 0), 2),
            ];
        }

        $filename = "VAT-D3-sales-{$startDate}-to-{$endDate}.csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows, $company, $startBs, $endBs, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header section (IRD prescribed format)
            fputcsv($handle, ['PAN', $company->pan ?? '']);
            fputcsv($handle, ['Business Name', $company->company_name ?? '']);
            fputcsv($handle, ['Period (AD)', "{$startDate} to {$endDate}"]);
            fputcsv($handle, ['Period (BS)', "{$startBs} to {$endBs}"]);
            fputcsv($handle, []);

            // Column headers — IRD D3 format includes zero-rated (export) as a separate column
            fputcsv($handle, [
                'Type', 'Invoice/Bill No', 'Date (AD)', 'Date (BS)',
                'Party PAN', 'Party Name',
                'Taxable Amount', 'VAT Amount', 'Zero-Rated Amount', 'Exempt Amount', 'Total Amount',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['type'],
                    $row['doc_no'],
                    $row['date_ad'],
                    $row['date_bs'],
                    $row['pan'],
                    $row['party_name'],
                    $row['taxable_amount'],
                    $row['vat_amount'],
                    $row['zero_rated_amount'],
                    $row['exempt_amount'],
                    $row['total_amount'],
                ]);
            }

            // Summary row
            fputcsv($handle, []);
            fputcsv($handle, [
                'TOTAL', '', '', '', '', '',
                round(array_sum(array_column($rows, 'taxable_amount')), 2),
                round(array_sum(array_column($rows, 'vat_amount')), 2),
                round(array_sum(array_column($rows, 'zero_rated_amount')), 2),
                round(array_sum(array_column($rows, 'exempt_amount')), 2),
                round(array_sum(array_column($rows, 'total_amount')), 2),
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function safeAdToBs(string $date): string
    {
        try {
            $bs = $this->nepaliDate->adToBs($date);

            return $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
        } catch (\Throwable) {
            return '';
        }
    }

    private function fetchSalesRows(int $companyId, string $startDate, string $endDate)
    {
        return DB::table('invoices')
            ->leftJoin('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoin('invoice_items', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->leftJoin('taxes', 'taxes.id', '=', 'invoice_items.tax_id')
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

    private function fetchCreditNoteRows(int $companyId, string $startDate, string $endDate)
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
}
