<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\FiscalYear;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Enums\PayrollStatusEnum;
use App\Services\PayrollService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\HR\PayrollRunResource;
use App\Http\Requests\Api\Admin\HR\PayrollRunRequest;

class PayrollController extends Controller
{
    public function __construct(protected PayrollService $payrollService) {}

    #[Permissions('list_payroll', group: 'payroll', desc: 'List Payroll Runs')]
    public function index(Request $request)
    {
        $runs = PayrollRun::with('fiscalYear')
            ->filter($request->all())
            ->latest()
            ->paginate($request->limit ?? 25);

        return PayrollRunResource::collection($runs);
    }

    #[Permissions('create_payroll', group: 'payroll', desc: 'Create Payroll Run')]
    public function store(PayrollRunRequest $request)
    {
        $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);

        abort_if(! $fiscalYear->is_current, 422, 'Payroll can only be created for the current fiscal year.');

        $run = DB::transaction(function () use ($request) {
            FiscalYear::withoutGlobalScopes()
                ->where('id', $request->fiscal_year_id)
                ->lockForUpdate()
                ->first();

            $exists = PayrollRun::where('fiscal_year_id', $request->fiscal_year_id)
                ->where('month', $request->month)
                ->exists();

            if ($exists) {
                abort(422, 'Payroll run already exists for this month.');
            }

            return PayrollRun::create([
                'company_id' => auth('admin')->user()->company_id,
                'fiscal_year_id' => $request->fiscal_year_id,
                'month' => $request->month,
                'status' => PayrollStatusEnum::DRAFT,
            ]);
        });

        return response()->json([
            'data' => PayrollRunResource::make($run->load('fiscalYear')),
            'message' => 'Payroll Run Created Successfully',
        ], 201);
    }

    #[Permissions('show_payroll', group: 'payroll', desc: 'Show Payroll Run')]
    public function show(PayrollRun $payrollRun)
    {
        return PayrollRunResource::make($payrollRun->load(['fiscalYear', 'payslips.employee', 'payslips.items']));
    }

    #[Permissions('edit_payroll', group: 'payroll', desc: 'Process Payroll Run')]
    public function process(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->status === PayrollStatusEnum::PAID, 403, 'Paid payroll cannot be reprocessed.');

        $payrollRun = $this->payrollService->calculate($payrollRun);

        return response()->json([
            'data' => PayrollRunResource::make($payrollRun->load(['fiscalYear', 'payslips.employee', 'payslips.items'])),
            'message' => 'Payroll Calculated Successfully',
        ]);
    }

    #[Permissions('edit_payroll', group: 'payroll', desc: 'Approve Payroll Run')]
    public function approve(PayrollRun $payrollRun)
    {
        $payrollRun = $this->payrollService->approve($payrollRun);

        return response()->json([
            'data' => PayrollRunResource::make($payrollRun->load('fiscalYear')),
            'message' => 'Payroll Approved Successfully',
        ]);
    }

    #[Permissions('edit_payroll', group: 'payroll', desc: 'Confirm Payroll Run as Paid')]
    public function confirm(Request $request, PayrollRun $payrollRun)
    {
        abort_if($payrollRun->status !== PayrollStatusEnum::PROCESSED, 403, 'Only processed payroll can be confirmed.');

        $request->validate([
            'paid_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        $journal = $this->payrollService->postToLedger($payrollRun, (int) $request->paid_account_id);

        return response()->json([
            'data' => PayrollRunResource::make($payrollRun->fresh()->load(['fiscalYear', 'journal', 'paidAccount'])),
            'message' => 'Payroll Confirmed as Paid and Posted to Ledger',
        ]);
    }

    #[Permissions('delete_payroll', group: 'payroll', desc: 'Delete Payroll Run')]
    public function destroy(PayrollRun $payrollRun)
    {
        abort_if($payrollRun->status === PayrollStatusEnum::PAID, 403, 'Paid payroll cannot be deleted.');

        $payrollRun->payslips()->each(fn ($p) => $p->items()->delete());
        $payrollRun->payslips()->delete();
        $payrollRun->delete();

        return response()->json(['message' => 'Payroll Run Deleted Successfully']);
    }
}
