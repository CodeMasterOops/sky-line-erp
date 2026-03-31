<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Payslip;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PayslipResource;

class PayslipController extends Controller
{
    /**
     * @Permissions("list_payroll", group="hr", desc="List Payslips")
     */
    public function index(Request $request)
    {
        $payslips = Payslip::with(['employee', 'payrollRun'])
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->payroll_run_id, fn ($q) => $q->where('payroll_run_id', $request->payroll_run_id))
            ->latest()
            ->paginate($request->limit ?? 25);

        return PayslipResource::collection($payslips);
    }

    /**
     * @Permissions("show_payroll", group="hr", desc="Show Payslip")
     */
    public function show(Payslip $payslip)
    {
        return PayslipResource::make($payslip->load(['employee.department', 'employee.designation', 'payrollRun', 'items']));
    }
}
