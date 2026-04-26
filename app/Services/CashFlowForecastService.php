<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Cheque;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowForecastService
{
    /**
     * Build a day-by-day cash flow forecast for the given number of days.
     * Returns an array with inflows, outflows, and running balance per day.
     */
    public function forecast(int $companyId, float $openingBalance, int $days = 90): array
    {
        $from = now()->toDateString();
        $to   = now()->addDays($days)->toDateString();

        $inflows  = $this->buildInflows($companyId, $from, $to);
        $outflows = $this->buildOutflows($companyId, $from, $to);
        $pdcIn    = $this->buildPdcInflows($companyId, $from, $to);
        $pdcOut   = $this->buildPdcOutflows($companyId, $from, $to);

        $daily = $this->mergeDailyRows($from, $to, $inflows, $outflows, $pdcIn, $pdcOut, $openingBalance);

        return [
            'from'           => $from,
            'to'             => $to,
            'opening_balance'=> $openingBalance,
            'daily'          => $daily,
            'summary'        => [
                'total_inflow'  => collect($daily)->sum('inflow'),
                'total_outflow' => collect($daily)->sum('outflow'),
                'closing_balance'=> collect($daily)->last()['running_balance'] ?? $openingBalance,
            ],
        ];
    }

    /** Unpaid invoices due in range (expected cash in) */
    private function buildInflows(int $companyId, string $from, string $to): Collection
    {
        return Invoice::where('company_id', $companyId)
            ->whereIn('status', ['approved', 'partial'])
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->selectRaw('due_date, SUM(grand_total - COALESCE(amount_received,0)) as expected_cash')
            ->groupBy('due_date')
            ->pluck('expected_cash', 'due_date');
    }

    /** Unpaid bills due in range (expected cash out) */
    private function buildOutflows(int $companyId, string $from, string $to): Collection
    {
        return Bill::where('company_id', $companyId)
            ->whereIn('status', ['approved', 'partial'])
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->selectRaw('due_date, SUM(grand_total - COALESCE(amount_paid,0)) as expected_cash')
            ->groupBy('due_date')
            ->pluck('expected_cash', 'due_date');
    }

    /** PDC receivable cheques maturing in range */
    private function buildPdcInflows(int $companyId, string $from, string $to): Collection
    {
        return Cheque::where('company_id', $companyId)
            ->where('type', 'receivable')
            ->where('status', 'pending')
            ->whereDate('cheque_date', '>=', $from)
            ->whereDate('cheque_date', '<=', $to)
            ->selectRaw('cheque_date, SUM(amount) as total')
            ->groupBy('cheque_date')
            ->pluck('total', 'cheque_date');
    }

    /** PDC payable cheques maturing in range */
    private function buildPdcOutflows(int $companyId, string $from, string $to): Collection
    {
        return Cheque::where('company_id', $companyId)
            ->where('type', 'payable')
            ->where('status', 'pending')
            ->whereDate('cheque_date', '>=', $from)
            ->whereDate('cheque_date', '<=', $to)
            ->selectRaw('cheque_date, SUM(amount) as total')
            ->groupBy('cheque_date')
            ->pluck('total', 'cheque_date');
    }

    private function mergeDailyRows(
        string $from, string $to,
        Collection $inflows, Collection $outflows,
        Collection $pdcIn, Collection $pdcOut,
        float $runningBalance
    ): array {
        $daily   = [];
        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);

        while ($current->lte($end)) {
            $dateKey  = $current->toDateString();
            $inflow   = (float) ($inflows[$dateKey] ?? 0) + (float) ($pdcIn[$dateKey] ?? 0);
            $outflow  = (float) ($outflows[$dateKey] ?? 0) + (float) ($pdcOut[$dateKey] ?? 0);
            $runningBalance += $inflow - $outflow;

            if ($inflow > 0 || $outflow > 0) {
                $daily[] = [
                    'date'            => $dateKey,
                    'inflow'          => round($inflow, 2),
                    'outflow'         => round($outflow, 2),
                    'net'             => round($inflow - $outflow, 2),
                    'running_balance' => round($runningBalance, 2),
                ];
            }

            $current->addDay();
        }

        return $daily;
    }
}
