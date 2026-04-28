<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use App\Models\Party;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Nepal\NepaliDateService;
use App\Services\Nepal\NepaliNumberService;

class TdsChallanController extends Controller
{
    public function __construct(
        private NepaliDateService $nepaliDate,
        private NepaliNumberService $nepaliNumber,
    ) {}

    /**
     * @Permissions("list_invoice", group="invoice", desc="TDS Challan Summary")
     *
     * Returns monthly TDS summary for challan generation.
     */
    public function summary(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $companyId = auth('admin')->user()->company_id;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $rows = DB::table('tds_deductions')
            ->leftJoin('parties', 'parties.id', '=', 'tds_deductions.party_id')
            ->leftJoin('journals', 'journals.id', '=', 'tds_deductions.journal_id')
            ->where('tds_deductions.company_id', $companyId)
            ->whereBetween(
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at))'),
                [$startDate, $endDate]
            )
            ->select([
                'tds_deductions.id',
                DB::raw("COALESCE(parties.name, 'N/A') as party_name"),
                DB::raw("COALESCE(parties.pan, '') as party_pan"),
                'tds_deductions.tds_category',
                'tds_deductions.tds_rate',
                'tds_deductions.base_amount',
                'tds_deductions.tds_amount',
                'tds_deductions.period_month',
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at)) as effective_date'),
            ])
            ->orderBy('effective_date')
            ->get();

        return response()->json([
            'data' => [
                'rows' => $rows,
                'summary' => [
                    'total_base' => round($rows->sum('base_amount'), 2),
                    'total_tds' => round($rows->sum('tds_amount'), 2),
                ],
            ],
        ]);
    }

    /**
     * @Permissions("list_invoice", group="invoice", desc="Download TDS Deposit Challan PDF")
     */
    public function downloadChallan(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'deposit_date' => 'nullable|date',
        ]);

        $companyId = auth('admin')->user()->company_id;
        $company = auth('admin')->user()->company;
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $depositDate = $request->get('deposit_date', now()->toDateString());

        $deductions = DB::table('tds_deductions')
            ->leftJoin('parties', 'parties.id', '=', 'tds_deductions.party_id')
            ->leftJoin('journals', 'journals.id', '=', 'tds_deductions.journal_id')
            ->where('tds_deductions.company_id', $companyId)
            ->whereBetween(
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at))'),
                [$startDate, $endDate]
            )
            ->select([
                DB::raw("COALESCE(parties.name, 'N/A') as party_name"),
                DB::raw("COALESCE(parties.pan, '') as party_pan"),
                'tds_deductions.tds_category',
                'tds_deductions.tds_rate',
                'tds_deductions.base_amount',
                'tds_deductions.tds_amount',
                'tds_deductions.period_month',
            ])
            ->orderBy('tds_deductions.tds_category')
            ->get();

        // Compute BS dates for the period
        $periodMonthBs = '';
        $taxYearBs = '';
        try {
            $sBs = $this->nepaliDate->adToBs($startDate);
            $periodMonthBs = $this->nepaliDate->monthName($sBs['month']).' '.$sBs['year'];
            $taxYearBs = $sBs['year'].'-'.($sBs['year'] + 1);
        } catch (\Throwable) {
        }

        // Use first revenue code (most common) or default
        $revenueCode = '11112';

        $totalBase = round($deductions->sum('base_amount'), 2);
        $totalTds = round($deductions->sum('tds_amount'), 2);
        $amountInWords = $this->nepaliNumber->amountToWords($totalTds);

        $pdf = Pdf::loadView('pdf.tds-challan', compact(
            'company', 'deductions', 'periodMonthBs', 'taxYearBs',
            'revenueCode', 'depositDate', 'totalBase', 'totalTds', 'amountInWords'
        ))->setPaper('A4', 'portrait');

        return $pdf->download("TDS-Challan-{$startDate}-to-{$endDate}.pdf");
    }

    /**
     * @Permissions("list_invoice", group="invoice", desc="Download TDS Withholding Certificate PDF")
     */
    public function downloadCertificate(Request $request)
    {
        $request->validate([
            'party_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $companyId = auth('admin')->user()->company_id;
        $company = auth('admin')->user()->company;
        $partyId = $request->get('party_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $party = Party::find($partyId);
        if (! $party || $party->company_id !== $companyId) {
            abort(404, 'Party not found.');
        }

        $deductions = DB::table('tds_deductions')
            ->leftJoin('journals', 'journals.id', '=', 'tds_deductions.journal_id')
            ->where('tds_deductions.company_id', $companyId)
            ->where('tds_deductions.party_id', $partyId)
            ->whereBetween(
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at))'),
                [$startDate, $endDate]
            )
            ->select([
                'tds_deductions.tds_category',
                'tds_deductions.tds_rate',
                'tds_deductions.base_amount',
                'tds_deductions.tds_amount',
                'tds_deductions.period_month',
            ])
            ->orderBy('tds_deductions.period_month')
            ->get();

        if ($deductions->isEmpty()) {
            return response()->json([
                'message' => 'No TDS deductions found for this party in the selected period.',
            ], 422);
        }

        $taxYearBs = '';
        $fiscalYearCode = $company->fiscalYear?->year_code ?? '';
        try {
            $sBs = $this->nepaliDate->adToBs($startDate);
            $taxYearBs = $sBs['year'].'-'.($sBs['year'] + 1);
        } catch (\Throwable) {
        }

        $totalBase = round($deductions->sum('base_amount'), 2);
        $totalTds = round($deductions->sum('tds_amount'), 2);
        $amountInWords = $this->nepaliNumber->amountToWords($totalTds);
        $certNo = strtoupper(substr(md5($partyId.$startDate.$endDate), 0, 8));

        $pdf = Pdf::loadView('pdf.tds-certificate', compact(
            'company', 'party', 'deductions', 'taxYearBs', 'fiscalYearCode',
            'totalBase', 'totalTds', 'amountInWords', 'certNo'
        ))->setPaper('A4', 'portrait');

        return $pdf->download("TDS-Certificate-{$party->name}-{$taxYearBs}.pdf");
    }
}
