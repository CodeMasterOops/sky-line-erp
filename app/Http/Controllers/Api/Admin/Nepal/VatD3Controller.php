<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\AccountReportService;
use App\Services\Nepal\NepaliDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * VAT D3 Return — generates the IRD-format D3 return export.
 *
 * The D3 form (Annex-D3 under VAT Rule 2053) is filed monthly/quarterly
 * and includes all sales and purchase transactions with VAT details.
 *
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
     * @Permissions("list_invoice", group="invoice", desc="Export VAT D3 CSV")
     *
     * Exports the VAT D3 return in IRD's prescribed CSV format.
     * Sales Annex (D3-Sales): invoice_no, date_bs, buyer_pan, buyer_name,
     *                         taxable_amount, vat_amount, exempt_amount, total_amount
     * Purchase Annex (D3-Purchase): bill_no, date_bs, seller_pan, seller_name,
     *                               taxable_amount, input_vat, exempt_amount, total_amount
     */
    public function exportCsv(Request $request)
    {
        $request->validate([
            'type'       => 'required|in:sales,purchase,combined',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $companyId = auth('admin')->user()->company_id;
        $company   = auth('admin')->user()->company;
        $type      = $request->get('type', 'combined');
        $startDate = $request->get('start_date');
        $endDate   = $request->get('end_date');

        $startBs = '';
        $endBs = '';
        try {
            $sBs = $this->nepaliDate->adToBs($startDate);
            $eBs = $this->nepaliDate->adToBs($endDate);
            $startBs = $this->nepaliDate->formatBs($sBs['year'], $sBs['month'], $sBs['day']);
            $endBs = $this->nepaliDate->formatBs($eBs['year'], $eBs['month'], $eBs['day']);
        } catch (\Throwable) {}

        $rows = [];

        if (in_array($type, ['sales', 'combined'])) {
            $sales = $this->fetchSalesRows($companyId, $startDate, $endDate);
            foreach ($sales as $row) {
                $dateBs = '';
                try {
                    $bs = $this->nepaliDate->adToBs($row->invoice_date);
                    $dateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
                } catch (\Throwable) {}

                $rows[] = [
                    'type'           => 'Sales',
                    'doc_no'         => $row->invoice_no,
                    'date_ad'        => $row->invoice_date,
                    'date_bs'        => $dateBs,
                    'pan'            => $row->buyer_pan ?? $row->party_pan ?? '',
                    'party_name'     => $row->party_name ?? 'Cash Customer',
                    'taxable_amount' => round($row->taxable_amount ?? 0, 2),
                    'vat_amount'     => round($row->vat_amount ?? 0, 2),
                    'exempt_amount'  => round($row->exempt_amount ?? 0, 2),
                    'total_amount'   => round(($row->taxable_amount ?? 0) + ($row->vat_amount ?? 0) + ($row->exempt_amount ?? 0), 2),
                ];
            }
        }

        if (in_array($type, ['purchase', 'combined'])) {
            $purchases = $this->fetchPurchaseRows($companyId, $startDate, $endDate);
            foreach ($purchases as $row) {
                $dateBs = '';
                try {
                    $bs = $this->nepaliDate->adToBs($row->bill_date);
                    $dateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
                } catch (\Throwable) {}

                $rows[] = [
                    'type'           => 'Purchase',
                    'doc_no'         => $row->bill_no,
                    'date_ad'        => $row->bill_date,
                    'date_bs'        => $dateBs,
                    'pan'            => $row->party_pan ?? '',
                    'party_name'     => $row->party_name ?? '',
                    'taxable_amount' => round($row->taxable_amount ?? 0, 2),
                    'vat_amount'     => round($row->input_vat ?? 0, 2),
                    'exempt_amount'  => round($row->exempt_amount ?? 0, 2),
                    'total_amount'   => round(($row->taxable_amount ?? 0) + ($row->input_vat ?? 0) + ($row->exempt_amount ?? 0), 2),
                ];
            }
        }

        // Generate CSV
        $filename = "VAT-D3-{$type}-{$startDate}-to-{$endDate}.csv";
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows, $company, $startBs, $endBs, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Header section (IRD prescribed format)
            fputcsv($handle, ['PAN', $company->pan ?? '']);
            fputcsv($handle, ['Business Name', $company->company_name ?? '']);
            fputcsv($handle, ['Period (AD)', "{$startDate} to {$endDate}"]);
            fputcsv($handle, ['Period (BS)', "{$startBs} to {$endBs}"]);
            fputcsv($handle, []);

            // Column headers
            fputcsv($handle, [
                'Type', 'Invoice/Bill No', 'Date (AD)', 'Date (BS)',
                'Party PAN', 'Party Name',
                'Taxable Amount', 'VAT Amount', 'Exempt Amount', 'Total Amount',
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
                round(array_sum(array_column($rows, 'exempt_amount')), 2),
                round(array_sum(array_column($rows, 'total_amount')), 2),
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
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
                'invoices.buyer_pan', 'parties.pan', 'parties.name'
            )
            ->select([
                'invoices.invoice_no',
                'invoices.invoice_date',
                'invoices.buyer_pan',
                'parties.pan as party_pan',
                'parties.name as party_name',
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN invoice_items.tax_amount ELSE 0 END) as vat_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'exempt' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
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
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'exempt' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as exempt_amount"),
            ])
            ->get();
    }
}
