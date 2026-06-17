<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;

/**
 * VAT D4 Return — generates the IRD-format D4 (purchase register) export.
 *
 * The D4 annex covers all purchase (Kharid) transactions with VAT details.
 * IRD accepted format: CSV with prescribed column order.
 */
class VatD4Controller extends Controller
{
    public function __construct(private NepaliDateService $nepaliDate) {}

    /**
     * @Permissions("list_bill", group="bill", desc="Export VAT D4 Purchase CSV")
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

        $purchases = $this->fetchPurchaseRows($companyId, $startDate, $endDate);
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

        $debitNotes = $this->fetchDebitNoteRows($companyId, $startDate, $endDate);
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

    private function fetchPurchaseRows(int $companyId, string $startDate, string $endDate)
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

    private function fetchDebitNoteRows(int $companyId, string $startDate, string $endDate)
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
