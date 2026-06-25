<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Nepal\VatReportService;
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
        private VatReportService $vatReport,
    ) {}

    #[Permissions('list_invoice', group: 'invoice', desc: 'VAT D3 Return Summary')]
    public function summary(Request $request)
    {
        $data = $this->reportService->vatReturn($request);

        return response()->json(['data' => $data]);
    }

    /**
     * Exports the VAT D3 sales register (Bikri Kitab) in IRD's prescribed CSV format.
     * Columns: invoice_no, date_bs, buyer PAN, buyer_name,
     *          taxable_amount, vat_amount, zero_rated_amount, exempt_amount, total_amount
     */
    #[Permissions('list_invoice', group: 'invoice', desc: 'Export VAT D3 Sales CSV')]
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

        $sales = $this->vatReport->fetchSalesRows($companyId, $startDate, $endDate);
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
        $creditNotes = $this->vatReport->fetchCreditNoteRows($companyId, $startDate, $endDate);
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
}
