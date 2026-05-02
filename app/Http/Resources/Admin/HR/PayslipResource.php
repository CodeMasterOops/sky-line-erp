<?php

namespace App\Http\Resources\Admin\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_run_id' => $this->payroll_run_id,
            'employee_id' => $this->employee_id,
            'working_days' => $this->working_days,
            'present_days' => $this->present_days,
            'leave_days' => $this->leave_days,
            'gross_salary' => $this->gross_salary,
            'total_deductions' => $this->total_deductions,
            'tds_amount' => $this->tds_amount ?? 0,
            'net_salary' => $this->net_salary,
            'employee' => $this->whenLoaded('employee', fn () => EmployeeResource::make($this->employee)),
            'payroll_run' => $this->whenLoaded('payrollRun', fn () => PayrollRunResource::make($this->payrollRun)),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'salary_component_id' => $item->salary_component_id,
                'component_name' => $item->component_name,
                'component_type' => $item->component_type,
                'amount' => $item->amount,
            ])),
        ];
    }
}
