<?php

namespace App\Services\Accounting;

use App\Models\AccountSetting;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for "can this sale be posted to the general ledger?".
 *
 * Both the standard invoice approval flow and POS checkout post a sales journal
 * (Accounts Receivable / Sales Revenue / VAT Output). If those control accounts
 * are not configured the posting would silently skip or write journal lines with
 * a null account, leaving the books inconsistent. This guard fails fast instead.
 */
class GlAccountConfigGuard
{
    /**
     * @throws ValidationException
     */
    public function assertSalesPostable(bool $hasTax): void
    {
        $missing = $this->missingSalesAccounts($hasTax);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post sale until accounting is configured. Missing: '
                    .implode(', ', $missing).'. Set these under Accounting → Account Settings.',
            ]);
        }
    }

    /**
     * @return list<string> Human-readable labels of the missing control accounts.
     */
    public function missingSalesAccounts(bool $hasTax, ?int $companyId = null): array
    {
        $settings = $companyId
            ? AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first()
            : AccountSetting::first();

        $missing = [];

        if (! $settings?->customer_account_id) {
            $missing[] = 'Accounts Receivable (customer) account';
        }

        if (! $settings?->sales_account_id) {
            $missing[] = 'Sales Revenue account';
        }

        if ($hasTax && ! $settings?->vat_account_id) {
            $missing[] = 'VAT Output account';
        }

        return $missing;
    }

    /**
     * @throws ValidationException
     */
    public function assertPurchasePostable(bool $hasTax, ?int $companyId = null): void
    {
        $missing = $this->missingPurchaseAccounts($hasTax, $companyId);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post purchase until accounting is configured. Missing: '
                    .implode(', ', $missing).'. Set these under Accounting → Account Settings.',
            ]);
        }
    }

    /**
     * @return list<string> Human-readable labels of the missing control accounts.
     */
    public function missingPurchaseAccounts(bool $hasTax, ?int $companyId = null): array
    {
        $settings = $companyId
            ? AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first()
            : AccountSetting::first();

        $missing = [];

        if (! $settings?->purchase_account_id) {
            $missing[] = 'Purchase account';
        }

        if (! $settings?->supplier_account_id) {
            $missing[] = 'Accounts Payable (supplier) account';
        }

        if ($hasTax && ! $settings?->vat_account_id) {
            $missing[] = 'VAT Input account';
        }

        return $missing;
    }

    /**
     * @throws ValidationException
     */
    public function assertExpensePostable(bool $hasTax, ?int $companyId = null): void
    {
        $missing = $this->missingExpenseAccounts($hasTax, $companyId);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post expense until accounting is configured. Missing: '
                    .implode(', ', $missing).'. Set these under Accounting → Account Settings.',
            ]);
        }
    }

    /**
     * Expense debit accounts come from the expense lines themselves; only the
     * payable (credit) side and VAT live in settings.
     *
     * @return list<string> Human-readable labels of the missing control accounts.
     */
    public function missingExpenseAccounts(bool $hasTax, ?int $companyId = null): array
    {
        $settings = $companyId
            ? AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first()
            : AccountSetting::first();

        $missing = [];

        if (! $settings?->supplier_account_id) {
            $missing[] = 'Accounts Payable (supplier) account';
        }

        if ($hasTax && ! $settings?->vat_account_id) {
            $missing[] = 'VAT Input account';
        }

        return $missing;
    }
}
