<?php

namespace App\Services\Nepal;

use App\Models\Company;
use App\Models\Invoice;
use App\Enums\StatusEnum;

/**
 * Reconciles the local invoice ledger against IRD EBS sync state and validates
 * that pending/failed invoices carry the fields IRD requires, so an accountant
 * can see — in one place — which invoices are out of compliance and why, and
 * fix the data before re-syncing.
 */
class IrdReconciliationService
{
    private const SYNC_STATUSES = ['pending', 'synced', 'failed', 'skipped'];

    /**
     * IRD-required fields that, when missing, will make an EBS sync fail.
     * Returns a list of missing field keys; an empty list means ready to sync.
     *
     * @return list<string>
     */
    public function missingMandatoryFields(Invoice $invoice, Company $company): array
    {
        $missing = [];

        if (! $company->ird_ebs_enabled) {
            $missing[] = 'ird_not_enabled';
        }

        if (empty($company->pan)) {
            $missing[] = 'company_pan';
        }

        foreach ([
            'ird_username', 'ird_password', 'ird_branch_office',
            'ird_unit_name', 'ird_fiscal_device',
        ] as $credential) {
            if (empty($company->{$credential})) {
                $missing[] = $credential;
            }
        }

        if (empty($invoice->invoice_no)) {
            $missing[] = 'invoice_no';
        }

        if (empty($invoice->invoice_date)) {
            $missing[] = 'invoice_date';
        }

        $hasItems = $invoice->relationLoaded('invoiceItems')
            ? $invoice->invoiceItems->isNotEmpty()
            : $invoice->invoiceItems()->exists();

        if (! $hasItems) {
            $missing[] = 'invoice_items';
        }

        return $missing;
    }

    public function credentialsComplete(Company $company): bool
    {
        return ! empty($company->pan)
            && ! empty($company->ird_username)
            && ! empty($company->ird_password)
            && ! empty($company->ird_branch_office)
            && ! empty($company->ird_unit_name)
            && ! empty($company->ird_fiscal_device);
    }

    /**
     * @return array{ird_enabled:bool, credentials_complete:bool, summary:array<string,int>, out_of_compliance_count:int, not_ready_count:int, out_of_compliance:list<array<string,mixed>>}
     */
    public function reconciliation(int $companyId, ?string $startDate = null, ?string $endDate = null): array
    {
        $company = Company::withoutGlobalScopes()->find($companyId);

        $base = Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($startDate, fn ($query) => $query->whereDate('invoice_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->whereDate('invoice_date', '<=', $endDate));

        $counts = (clone $base)
            ->selectRaw('ird_sync_status, COUNT(*) as count')
            ->groupBy('ird_sync_status')
            ->pluck('count', 'ird_sync_status');

        $summary = [];
        foreach (self::SYNC_STATUSES as $status) {
            $summary[$status] = (int) ($counts[$status] ?? 0);
        }

        $outstanding = (clone $base)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->whereIn('ird_sync_status', ['pending', 'failed'])
            ->with('invoiceItems:id,invoice_id')
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get(['id', 'invoice_no', 'invoice_date', 'ird_sync_status', 'ird_error']);

        $rows = $outstanding->map(function (Invoice $invoice) use ($company) {
            $missing = $company ? $this->missingMandatoryFields($invoice, $company) : ['company_not_found'];

            return [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date,
                'sync_status' => $invoice->ird_sync_status,
                'error' => $invoice->ird_error,
                'missing_fields' => $missing,
                'ready_to_sync' => $missing === [],
            ];
        });

        return [
            'ird_enabled' => (bool) $company?->ird_ebs_enabled,
            'credentials_complete' => $company ? $this->credentialsComplete($company) : false,
            'summary' => $summary,
            'out_of_compliance_count' => $rows->count(),
            'not_ready_count' => $rows->where('ready_to_sync', false)->count(),
            'out_of_compliance' => $rows->values()->all(),
        ];
    }
}
