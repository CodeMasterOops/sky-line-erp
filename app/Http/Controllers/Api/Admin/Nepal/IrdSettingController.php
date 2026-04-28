<?php

namespace App\Http\Controllers\Api\Admin\Nepal;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Jobs\SyncInvoiceToIrdJob;
use App\Http\Controllers\Controller;

class IrdSettingController extends Controller
{
    /**
     * @Permissions("edit_setting", group="setting", desc="Get IRD EBS Settings")
     */
    public function show(Request $request)
    {
        $company = auth('admin')->user()->company;

        return response()->json([
            'data' => [
                'ird_username' => $company->ird_username,
                'ird_branch_office' => $company->ird_branch_office,
                'ird_unit_name' => $company->ird_unit_name,
                'ird_fiscal_device' => $company->ird_fiscal_device,
                'ird_ebs_enabled' => $company->ird_ebs_enabled,
                'has_password' => ! empty($company->ird_password),
            ],
        ]);
    }

    /**
     * @Permissions("edit_setting", group="setting", desc="Save IRD EBS Settings")
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'ird_username' => 'nullable|string|max:100',
            'ird_password' => 'nullable|string|max:255',
            'ird_branch_office' => 'nullable|string|max:50',
            'ird_unit_name' => 'nullable|string|max:200',
            'ird_fiscal_device' => 'nullable|string|max:100',
            'ird_ebs_enabled' => 'boolean',
        ]);

        $company = auth('admin')->user()->company;

        $updateData = [
            'ird_username' => $validated['ird_username'] ?? $company->ird_username,
            'ird_branch_office' => $validated['ird_branch_office'] ?? $company->ird_branch_office,
            'ird_unit_name' => $validated['ird_unit_name'] ?? $company->ird_unit_name,
            'ird_fiscal_device' => $validated['ird_fiscal_device'] ?? $company->ird_fiscal_device,
            'ird_ebs_enabled' => $validated['ird_ebs_enabled'] ?? $company->ird_ebs_enabled,
        ];

        // Only update password if a new one is provided
        if (! empty($validated['ird_password'])) {
            $updateData['ird_password'] = $validated['ird_password'];
        }

        Company::withoutGlobalScopes()->where('id', $company->id)->first()->update($updateData);

        return response()->json(['message' => 'IRD settings updated successfully.']);
    }

    /**
     * @Permissions("edit_setting", group="setting", desc="Manually trigger IRD sync for an invoice")
     */
    public function retrySync(Invoice $invoice)
    {
        if ($invoice->company_id !== auth('admin')->user()->company_id) {
            abort(403);
        }

        $invoice->update(['ird_sync_status' => 'pending', 'ird_error' => null]);
        SyncInvoiceToIrdJob::dispatch($invoice)->onQueue('ird');

        return response()->json(['message' => 'IRD sync queued. Status will update shortly.']);
    }

    /**
     * @Permissions("list_invoice", group="invoice", desc="IRD sync status summary")
     */
    public function syncSummary(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;

        $counts = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->selectRaw('ird_sync_status, COUNT(*) as count')
            ->groupBy('ird_sync_status')
            ->pluck('count', 'ird_sync_status');

        $failed = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('ird_sync_status', 'failed')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'invoice_no', 'invoice_date', 'ird_error', 'updated_at']);

        return response()->json([
            'data' => [
                'summary' => $counts,
                'failed' => $failed,
            ],
        ]);
    }
}
