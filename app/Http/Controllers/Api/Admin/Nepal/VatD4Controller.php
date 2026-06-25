<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Nepal\VatReportService;
use App\Services\Nepal\NepaliDateService;

/**
 * VAT D4 Return — generates the IRD-format D4 (purchase register) export.
 *
 * The D4 annex covers all purchase (Kharid) transactions with VAT details.
 * IRD accepted format: CSV with prescribed column order.
 */
class VatD4Controller extends Controller
{
    public function __construct(
        private NepaliDateService $nepaliDate,
        private VatReportService $vatReport,
    ) {}

    #[Permissions('list_bill', group: 'bill', desc: 'Export VAT D4 Purchase CSV')]
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

        $purchases = $this->vatReport->fetchPurchaseRows($companyId, $startDate, $endDate);
        foreach ($purchases as $row) {
            $dateBs = $this->safeAdToBs($row->bill_date);
            $rows[] = [
                'type' => 'Purchase',
                'doc_no' => $row->bill_no,
                'date_ad' => $row->bill_date,
                'date_bs' => $dateBs,
                'pan' => $row->party_pan ?? '',
                'party_name' => $row->party_name ?? '',
                'taxable_amount' => round($row->taxable_amount ?? 0, 2),
                'vat_amount' => round($row->input_vat ?? 0, 2),
                'zero_rated_amount' => round($row->zero_rated_amount ?? 0, 2),
                'exempt_amount' => round($row->exempt_amount ?? 0, 2),
                'total_amount' => round(($row->taxable_amount ?? 0) + ($row->input_vat ?? 0) + ($row->zero_rated_amount ?? 0) + ($row->exempt_amount ?? 0), 2),
            ];
        }

        $debitNotes = $this->vatReport->fetchDebitNoteRows($companyId, $startDate, $endDate);
        foreach ($debitNotes as $row) {
            $dateBs = $this->safeAdToBs($row->debit_note_date);
            $rows[] = [
                'type' => 'Debit Note',
                'doc_no' => $row->debit_note_no,
                'date_ad' => $row->debit_note_date,
                'date_bs' => $dateBs,
                'pan' => $row->party_pan ?? '',
                'party_name' => $row->party_name ?? '',
                'taxable_amount' => -round($row->taxable_amount ?? 0, 2),
                'vat_amount' => -round($row->input_vat ?? 0, 2),
                'zero_rated_amount' => -round($row->zero_rated_amount ?? 0, 2),
                'exempt_amount' => -round($row->exempt_amount ?? 0, 2),
                'total_amount' => -round(($row->taxable_amount ?? 0) + ($row->input_vat ?? 0) + ($row->zero_rated_amount ?? 0) + ($row->exempt_amount ?? 0), 2),
            ];
        }

        $filename = "VAT-D4-purchase-{$startDate}-to-{$endDate}.csv";
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows, $company, $startBs, $endBs, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['PAN', $company->pan ?? '']);
            fputcsv($handle, ['Business Name', $company->company_name ?? '']);
            fputcsv($handle, ['Period (AD)', "{$startDate} to {$endDate}"]);
            fputcsv($handle, ['Period (BS)', "{$startBs} to {$endBs}"]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'Type', 'Bill No', 'Date (AD)', 'Date (BS)',
                'Supplier PAN', 'Supplier Name',
                'Taxable Amount', 'Input VAT', 'Zero-Rated Amount', 'Exempt Amount', 'Total Amount',
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
