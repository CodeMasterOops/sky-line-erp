<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $monthLabel = $this->month ? date('F', mktime(0, 0, 0, $this->month, 1)) : '';
        $yearName = $this->fiscalYear?->year_name ?? '';

        return [
            'id' => $this->id,
            'fiscal_year_id' => $this->fiscal_year_id,
            'fiscal_year' => $this->whenLoaded('fiscalYear', fn () => [
                'id' => $this->fiscalYear->id,
                'year_name' => $this->fiscalYear->year_name,
                'year_code' => $this->fiscalYear->year_code,
            ]),
            'month' => $this->month,
            'period_label' => $monthLabel ? "{$monthLabel} - {$yearName}" : $yearName,
            'month_year_label' => $monthLabel ? "{$monthLabel} - {$yearName}" : $yearName,
            'status' => $this->status ?? '',
            'status_label' => $this->status?->label() ?? '',
            'total_gross' => $this->total_gross,
            'total_deductions' => $this->total_deductions,
            'total_net' => $this->total_net,
            'processed_at' => $this->processed_at?->format('Y-m-d H:i'),
            'journal_id' => $this->journal_id,
            'paid_account_id' => $this->paid_account_id,
            'paid_at' => $this->paid_at?->format('Y-m-d H:i'),
            'paid_account' => $this->whenLoaded('paidAccount', fn () => [
                'id' => $this->paidAccount->id,
                'name' => $this->paidAccount->name,
            ]),
            'payslips' => $this->whenLoaded('payslips', fn () => PayslipResource::collection($this->payslips)),
        ];
    }
}
