<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use App\Models\Bill;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\CreditNote;
use App\Models\FiscalYear;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountReportService
{
    public function trialBalance(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $groups = $this->loadSortedRootGroups();
        $balances = $this->fetchBalancesForPeriod($groups, $period['start_date'], $period['end_date']);

        $rows = $groups
            ->map(fn (AccountGroup $group) => $this->transformTrialBalanceGroup($group, $balances))
            ->values()
            ->all();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $period['start_date']->format('d-m-Y'),
                    $period['end_date']->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'rows' => $rows,
            'summary' => $this->summarizeTrialBalanceRows($rows),
        ];
    }

    public function balanceSheet(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $compareFiscalYear = $this->resolveCompareFiscalYear($request, $period['fiscal_year']);
        $rootGroups = $this->loadSortedRootGroups()
            ->filter(fn (AccountGroup $group) => in_array(strtolower($group->name), ['assets', 'liabilities', 'equity']))
            ->values();

        $currentAmounts = $this->fetchNetAmountsForDate($rootGroups, $period['end_date']);
        $previousAmounts = $compareFiscalYear
            ? $this->fetchNetAmountsForFiscalYear($rootGroups, $compareFiscalYear)
            : collect();

        $rows = $rootGroups
            ->map(fn (AccountGroup $group) => $this->transformBalanceSheetGroup($group, $currentAmounts, $previousAmounts))
            ->values()
            ->all();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $period['start_date']->format('d-m-Y'),
                    $period['end_date']->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'compare_fiscal_year' => $this->mapFiscalYear($compareFiscalYear),
            'compare_period' => $compareFiscalYear?->end_date ? [
                'end_date' => $compareFiscalYear->end_date->toDateString(),
                'label' => $compareFiscalYear->end_date->format('d-m-Y'),
            ] : null,
            'rows' => $rows,
            'summary' => [
                'amount' => round(collect($rows)->sum('amount'), 2),
                'prev_amount' => round(collect($rows)->sum('prev_amount'), 2),
            ],
        ];
    }

    public function profitLoss(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $compareFiscalYear = $this->resolveCompareFiscalYear($request, $period['fiscal_year']);
        $rootGroups = $this->loadSortedRootGroups()
            ->filter(fn (AccountGroup $group) => in_array(strtolower($group->name), ['income', 'expenses']))
            ->values();

        $currentAmounts = $this->fetchPeriodNetAmounts($rootGroups, $period['start_date'], $period['end_date']);
        $previousAmounts = $compareFiscalYear
            ? $this->fetchNetAmountsForFiscalYear($rootGroups, $compareFiscalYear)
            : collect();

        $rows = $rootGroups
            ->map(fn (AccountGroup $group) => $this->transformAmountTreeGroup($group, $currentAmounts, $previousAmounts))
            ->values()
            ->all();

        $incomeRow = collect($rows)->first(fn ($row) => strtolower($row['name']) === 'income');
        $expenseRow = collect($rows)->first(fn ($row) => strtolower($row['name']) === 'expenses');

        $incomeAmount = (float) ($incomeRow['amount'] ?? 0);
        $expenseAmount = (float) ($expenseRow['amount'] ?? 0);
        $incomePrevAmount = (float) ($incomeRow['prev_amount'] ?? 0);
        $expensePrevAmount = (float) ($expenseRow['prev_amount'] ?? 0);

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $period['start_date']->format('d-m-Y'),
                    $period['end_date']->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'compare_fiscal_year' => $this->mapFiscalYear($compareFiscalYear),
            'rows' => $rows,
            'summary' => [
                'income' => $incomeAmount,
                'expense' => $expenseAmount,
                'net_profit' => round($incomeAmount - $expenseAmount, 2),
                'prev_income' => $incomePrevAmount,
                'prev_expense' => $expensePrevAmount,
                'prev_net_profit' => round($incomePrevAmount - $expensePrevAmount, 2),
            ],
        ];
    }

    public function journalReport(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $journalType = $request->input('journal_type');

        if (! empty($journalType) && ! collect(JournalTypeEnum::cases())->pluck('value')->contains($journalType)) {
            abort(422, 'Invalid journal type selected.');
        }

        $journals = Journal::query()
            ->with(['journalItems.account'])
            ->where('company_id', auth('admin')->user()->company_id)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('deleted_at')
            ->whereBetween('date', [$period['start_date']->toDateString(), $period['end_date']->toDateString()])
            ->when($journalType, fn ($query) => $query->where('type', $journalType))
            ->orderBy('date')
            ->orderBy('voucher_no')
            ->get();

        $rows = $journals->values()->map(function (Journal $journal, int $index) {
            $items = $journal->journalItems->map(fn ($item) => [
                'id' => $item->id,
                'particular' => $item->account?->name ?: '-',
                'dr_amount' => round((float) $item->dr_amount, 2),
                'cr_amount' => round((float) $item->cr_amount, 2),
            ])->values()->all();

            return [
                'id' => $journal->id,
                'sn' => $index + 1,
                'date' => $journal->date?->toDateString(),
                'voucher_no' => $journal->voucher_no,
                'type' => $journal->type?->value ?? '',
                'type_label' => $journal->type?->label() ?? '',
                'reference_no' => $journal->reference_no,
                'remarks' => $journal->remarks,
                'items' => $items,
                'total_dr' => round(collect($items)->sum('dr_amount'), 2),
                'total_cr' => round(collect($items)->sum('cr_amount'), 2),
            ];
        })->all();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $period['start_date']->format('d-m-Y'),
                    $period['end_date']->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'journal_type' => $journalType,
            'journal_type_options' => collect(JournalTypeEnum::cases())->map(fn ($type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
            'rows' => $rows,
            'summary' => [
                'total_dr' => round(collect($rows)->sum('total_dr'), 2),
                'total_cr' => round(collect($rows)->sum('total_cr'), 2),
            ],
        ];
    }

    public function generalLedger(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $accounts = Account::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'label' => trim(sprintf('%s (%s)', $account->name, $account->code)),
            ])
            ->values()
            ->all();

        $accountId = $validated['account_id'] ?? null;
        $selectedAccount = $accountId ? Account::find($accountId) : null;

        if (! $selectedAccount) {
            return [
                'period' => [
                    'start_date' => $period['start_date']->toDateString(),
                    'end_date' => $period['end_date']->toDateString(),
                    'label' => sprintf(
                        'For the period %s to %s',
                        $period['start_date']->format('d-m-Y'),
                        $period['end_date']->format('d-m-Y')
                    ),
                ],
                'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
                'selected_account' => null,
                'account_options' => $accounts,
                'rows' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_dr' => 0,
                    'total_cr' => 0,
                    'closing_balance' => 0,
                ],
            ];
        }

        $openingBalance = $this->fetchLedgerOpeningBalance($selectedAccount->id, $period['start_date']);
        $ledgerItems = $this->fetchLedgerEntries($selectedAccount->id, $period['start_date'], $period['end_date']);

        $runningBalance = $openingBalance;
        $rows = [[
            'type' => 'opening',
            'date' => null,
            'reference' => null,
            'remarks' => 'Balance brought forward',
            'debit' => 0,
            'credit' => 0,
            'balance' => round($openingBalance, 2),
        ]];

        foreach ($ledgerItems as $item) {
            $debit = round((float) $item->dr_amount, 2);
            $credit = round((float) $item->cr_amount, 2);
            $runningBalance = round($runningBalance + $credit - $debit, 2);

            $rows[] = [
                'type' => 'entry',
                'date' => $item->journal?->date?->toDateString(),
                'reference' => $item->journal?->voucher_no ?: '',
                'remarks' => $item->remarks ?: $item->journal?->remarks ?: '',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $runningBalance,
            ];
        }

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf(
                    'For the period %s to %s',
                    $period['start_date']->format('d-m-Y'),
                    $period['end_date']->format('d-m-Y')
                ),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'selected_account' => [
                'id' => $selectedAccount->id,
                'name' => $selectedAccount->name,
                'code' => $selectedAccount->code,
                'label' => trim(sprintf('%s (%s)', $selectedAccount->name, $selectedAccount->code)),
            ],
            'account_options' => $accounts,
            'rows' => $rows,
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_dr' => round($ledgerItems->sum('dr_amount'), 2),
                'total_cr' => round($ledgerItems->sum('cr_amount'), 2),
                'closing_balance' => round($runningBalance, 2),
            ],
        ];
    }

    public function vatSalesRegister(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $companyId = auth('admin')->user()->company_id;
        $start = $period['start_date']->toDateString();
        $end = $period['end_date']->toDateString();

        $dbRows = DB::table('invoices')
            ->leftJoin('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoin('invoice_items', function ($join) {
                $join->on('invoice_items.invoice_id', '=', 'invoices.id')
                    ->whereNull('invoice_items.deleted_at');
            })
            ->where('invoices.company_id', $companyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$start, $end])
            ->groupBy('invoices.id', 'invoices.invoice_no', 'invoices.bijak_no', 'invoices.invoice_date', 'parties.pan', 'parties.name')
            ->orderBy('invoices.invoice_date')
            ->orderBy('invoices.invoice_no')
            ->select([
                'invoices.invoice_no',
                'invoices.bijak_no',
                'invoices.invoice_date',
                DB::raw("COALESCE(parties.name, '-') as buyer_name"),
                DB::raw("COALESCE(parties.pan, '-') as buyer_pan"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'taxable' THEN invoice_items.tax_amount ELSE 0 END) as vat_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'exempt' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as exempt_amount"),
                DB::raw("SUM(CASE WHEN invoice_items.tax_line_type = 'zero_rated' THEN (invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount ELSE 0 END) as zero_rated_amount"),
            ])
            ->get();

        $rows = $dbRows->map(fn ($row) => [
            'date' => $row->invoice_date,
            'bijak_no' => $row->bijak_no ?: $row->invoice_no,
            'buyer_name' => $row->buyer_name,
            'buyer_pan' => $row->buyer_pan,
            'taxable_amount' => round((float) ($row->taxable_amount ?? 0), 2),
            'vat_amount' => round((float) ($row->vat_amount ?? 0), 2),
            'exempt_amount' => round((float) ($row->exempt_amount ?? 0), 2),
            'zero_rated_amount' => round((float) ($row->zero_rated_amount ?? 0), 2),
            'total_amount' => round((float) (($row->taxable_amount ?? 0) + ($row->vat_amount ?? 0) + ($row->exempt_amount ?? 0) + ($row->zero_rated_amount ?? 0)), 2),
        ])->values()->all();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf('For the period %s to %s', $period['start_date']->format('d-m-Y'), $period['end_date']->format('d-m-Y')),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'rows' => $rows,
            'summary' => [
                'taxable_amount' => round(collect($rows)->sum('taxable_amount'), 2),
                'vat_amount' => round(collect($rows)->sum('vat_amount'), 2),
                'exempt_amount' => round(collect($rows)->sum('exempt_amount'), 2),
                'zero_rated_amount' => round(collect($rows)->sum('zero_rated_amount'), 2),
                'total_amount' => round(collect($rows)->sum('total_amount'), 2),
            ],
        ];
    }

    public function vatPurchaseRegister(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $companyId = auth('admin')->user()->company_id;
        $start = $period['start_date']->toDateString();
        $end = $period['end_date']->toDateString();

        $dbRows = DB::table('bills')
            ->leftJoin('parties', 'parties.id', '=', 'bills.party_id')
            ->leftJoin('bill_items', function ($join) {
                $join->on('bill_items.bill_id', '=', 'bills.id')
                    ->whereNull('bill_items.deleted_at');
            })
            ->where('bills.company_id', $companyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$start, $end])
            ->groupBy('bills.id', 'bills.bill_no', 'bills.bill_date', 'parties.pan', 'parties.name')
            ->orderBy('bills.bill_date')
            ->orderBy('bills.bill_no')
            ->select([
                'bills.bill_no',
                'bills.bill_date',
                DB::raw("COALESCE(parties.name, '-') as supplier_name"),
                DB::raw("COALESCE(parties.pan, '-') as supplier_pan"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'taxable' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as taxable_amount"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'taxable' THEN bill_items.tax_amount ELSE 0 END) as input_vat"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'exempt' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as exempt_amount"),
                DB::raw("SUM(CASE WHEN bill_items.tax_line_type = 'zero_rated' THEN (bill_items.quantity * bill_items.rate) - bill_items.discount_amount ELSE 0 END) as zero_rated_amount"),
            ])
            ->get();

        $rows = $dbRows->map(fn ($row) => [
            'date' => $row->bill_date,
            'bill_no' => $row->bill_no,
            'supplier_name' => $row->supplier_name,
            'supplier_pan' => $row->supplier_pan,
            'taxable_amount' => round((float) ($row->taxable_amount ?? 0), 2),
            'input_vat' => round((float) ($row->input_vat ?? 0), 2),
            'exempt_amount' => round((float) ($row->exempt_amount ?? 0), 2),
            'zero_rated_amount' => round((float) ($row->zero_rated_amount ?? 0), 2),
            'total_amount' => round((float) (($row->taxable_amount ?? 0) + ($row->input_vat ?? 0) + ($row->exempt_amount ?? 0) + ($row->zero_rated_amount ?? 0)), 2),
        ])->values()->all();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf('For the period %s to %s', $period['start_date']->format('d-m-Y'), $period['end_date']->format('d-m-Y')),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'rows' => $rows,
            'summary' => [
                'taxable_amount' => round(collect($rows)->sum('taxable_amount'), 2),
                'input_vat' => round(collect($rows)->sum('input_vat'), 2),
                'exempt_amount' => round(collect($rows)->sum('exempt_amount'), 2),
                'zero_rated_amount' => round(collect($rows)->sum('zero_rated_amount'), 2),
                'total_amount' => round(collect($rows)->sum('total_amount'), 2),
            ],
        ];
    }

    public function vatReturn(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $salesData = $this->vatSalesRegister($request);
        $purchaseData = $this->vatPurchaseRegister($request);

        $outputVat = $salesData['summary']['vat_amount'];
        $inputVat = $purchaseData['summary']['input_vat'];
        $netVatPayable = round($outputVat - $inputVat, 2);

        return [
            'period' => $salesData['period'],
            'fiscal_year' => $salesData['fiscal_year'],
            'sales' => [
                'taxable_amount' => $salesData['summary']['taxable_amount'],
                'output_vat' => $salesData['summary']['vat_amount'],
                'exempt_amount' => $salesData['summary']['exempt_amount'],
                'zero_rated_amount' => $salesData['summary']['zero_rated_amount'],
                'total_sales' => $salesData['summary']['total_amount'],
            ],
            'purchases' => [
                'taxable_amount' => $purchaseData['summary']['taxable_amount'],
                'input_vat' => $purchaseData['summary']['input_vat'],
                'exempt_amount' => $purchaseData['summary']['exempt_amount'],
                'total_purchases' => $purchaseData['summary']['total_amount'],
            ],
            'net_vat_payable' => $netVatPayable,
            'sales_rows' => $salesData['rows'],
            'purchase_rows' => $purchaseData['rows'],
        ];
    }

    /**
     * Reconciles the VAT return computed from documents (output VAT on sales
     * invoices − input VAT on purchase bills) against the GL VAT control account
     * balance for the same period. A single vat_account_id carries both sides, so
     * its net (CR − DR) over the period should equal the net VAT payable.
     *
     * A non-zero delta surfaces: invoices/bills whose VAT wasn't posted to the
     * GL, manual VAT journal adjustments, or returns (credit/debit notes adjust
     * the GL VAT account but are not part of the invoice/bill-only computed
     * figure, so they legitimately appear here as a delta to investigate).
     *
     * @return array{period:array, fiscal_year:mixed, account_configured:bool, output_vat:float, input_vat:float, computed_net_vat:float, gl_net_vat:float, delta:float, reconciled:bool}
     */
    public function vatReturnReconciliation(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $period = $this->resolvePeriod($request);
        $return = $this->vatReturn($request);

        $computedNet = (float) $return['net_vat_payable'];

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        $vatAccountId = $settings?->vat_account_id;

        $base = [
            'period' => $return['period'],
            'fiscal_year' => $return['fiscal_year'],
            'output_vat' => $return['sales']['output_vat'],
            'input_vat' => $return['purchases']['input_vat'],
            'computed_net_vat' => round($computedNet, 2),
        ];

        if (! $vatAccountId) {
            return $base + [
                'account_configured' => false,
                'gl_net_vat' => 0.0,
                'delta' => round($computedNet, 2),
                'reconciled' => false,
            ];
        }

        $sums = DB::table('journal_items')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereNull('journal_items.deleted_at')
            ->where('journal_items.account_id', $vatAccountId)
            ->whereBetween('journals.date', [
                $period['start_date']->toDateString(),
                $period['end_date']->toDateString(),
            ])
            ->selectRaw('COALESCE(SUM(journal_items.dr_amount), 0) AS dr, COALESCE(SUM(journal_items.cr_amount), 0) AS cr')
            ->first();

        // VAT payable is a credit-side liability: output VAT (CR) − input VAT (DR).
        $glNet = round((float) $sums->cr - (float) $sums->dr, 2);
        $delta = round($computedNet - $glNet, 2);

        return $base + [
            'account_configured' => true,
            'gl_net_vat' => $glNet,
            'delta' => $delta,
            'reconciled' => ! JournalBalanceGuard::exceedsTolerance($delta),
        ];
    }

    public function cashFlow(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $companyId = auth('admin')->user()->company_id;

        $accountGroups = AccountGroup::with(['accounts', 'childrenRecursive'])
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->get();

        // Resolve a root group's cash-flow classification using account_type when
        // set, falling back to name-based matching for unclassified groups.
        $resolveCashFlowCategory = function (AccountGroup $rootGroup): string {
            if ($rootGroup->account_type !== null) {
                return match ($rootGroup->account_type) {
                    \App\Enums\AccountGroupTypeEnum::Income,
                    \App\Enums\AccountGroupTypeEnum::Expense => 'operating',
                    \App\Enums\AccountGroupTypeEnum::Asset => 'investing',
                    \App\Enums\AccountGroupTypeEnum::Liability,
                    \App\Enums\AccountGroupTypeEnum::Equity => 'financing',
                };
            }

            $name = strtolower($rootGroup->name);

            if (in_array($name, ['income', 'incomes', 'revenue', 'revenues', 'expense', 'expenses', 'expenditure', 'expenditures', 'costs', 'operating expenses'], true)) {
                return 'operating';
            }
            if (in_array($name, ['asset', 'assets'], true)) {
                return 'investing';
            }
            if (in_array($name, ['liability', 'liabilities', 'equity', 'equities'], true)) {
                return 'financing';
            }

            return '';
        };

        // Build account_id → category map, propagating the root group's category
        // down through all descendants so nested accounts are classified correctly.
        $collectAccountCategory = function (Collection $groups, string $category) use (&$collectAccountCategory): array {
            $map = [];
            foreach ($groups as $group) {
                foreach ($group->accounts as $account) {
                    $map[$account->id] = $category;
                }
                if ($group->childrenRecursive->isNotEmpty()) {
                    $map += $collectAccountCategory($group->childrenRecursive, $category);
                }
            }

            return $map;
        };

        $accountCategoryMap = [];
        foreach ($accountGroups as $rootGroup) {
            $category = $resolveCashFlowCategory($rootGroup);
            if ($category === '') {
                continue;
            }
            $accountCategoryMap[$rootGroup->id] = $category;
            foreach ($rootGroup->accounts as $account) {
                $accountCategoryMap[$account->id] = $category;
            }
            if ($rootGroup->childrenRecursive->isNotEmpty()) {
                $accountCategoryMap += $collectAccountCategory($rootGroup->childrenRecursive, $category);
            }
        }

        $items = JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.cr_amount - journal_items.dr_amount) as net')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereBetween('journals.date', [$period['start_date']->toDateString(), $period['end_date']->toDateString()])
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id');

        $operating = 0;
        $investing = 0;
        $financing = 0;

        foreach ($items as $accountId => $row) {
            $category = $accountCategoryMap[$accountId] ?? '';
            $net = (float) $row->net;

            if ($category === 'operating') {
                $operating += $net;
            } elseif ($category === 'investing') {
                $investing += -$net;
            } elseif ($category === 'financing') {
                $financing += $net;
            }
        }

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf('For the period %s to %s', $period['start_date']->format('d-m-Y'), $period['end_date']->format('d-m-Y')),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'operating' => round($operating, 2),
            'investing' => round($investing, 2),
            'financing' => round($financing, 2),
            'net_change' => round($operating + $investing + $financing, 2),
        ];
    }

    public function arAging(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $asOf = $period['end_date'];
        $companyId = auth('admin')->user()->company_id;

        $invoices = Invoice::with(['party', 'invoiceItems'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->get();

        $invoiceIds = $invoices->pluck('id');

        $paidByInvoice = DB::table('receipt_allocations')
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNull('deleted_at')
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as paid')
            ->pluck('paid', 'invoice_id');

        // Pre-aggregate approved, non-voided credit note totals by invoice_id.
        $creditNoteOffsetByInvoice = DB::table('credit_note_items')
            ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
            ->whereIn('credit_notes.invoice_id', $invoiceIds)
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereNull('credit_note_items.deleted_at')
            ->groupBy('credit_notes.invoice_id')
            ->selectRaw('credit_notes.invoice_id, COALESCE(SUM((credit_note_items.quantity * credit_note_items.rate) - credit_note_items.discount_amount + credit_note_items.tax_amount), 0) as offset_amount')
            ->pluck('offset_amount', 'invoice_id');

        $invoices = $invoices->filter(function (Invoice $invoice) use ($paidByInvoice, $creditNoteOffsetByInvoice) {
            $paid = (float) ($paidByInvoice->get($invoice->id, 0));
            $creditNoteOffset = (float) ($creditNoteOffsetByInvoice->get($invoice->id, 0));
            $total = $invoice->invoiceItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);

            return round($total - $paid - $creditNoteOffset, 2) > 0;
        });

        $rows = $invoices->map(function (Invoice $invoice) use ($asOf, $paidByInvoice, $creditNoteOffsetByInvoice) {
            $paid = (float) ($paidByInvoice->get($invoice->id, 0));
            $creditNoteOffset = (float) ($creditNoteOffsetByInvoice->get($invoice->id, 0));
            $total = $invoice->invoiceItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);
            $outstanding = round($total - $paid - $creditNoteOffset, 2);
            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date) : Carbon::parse($invoice->invoice_date)->addDays(30);
            $daysOverdue = max(0, $asOf->diffInDays($dueDate, false) * -1);

            return [
                'invoice_no' => $invoice->invoice_no,
                'party_name' => $invoice->party?->name ?? '-',
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $dueDate->toDateString(),
                'days_overdue' => $daysOverdue,
                'outstanding' => $outstanding,
                'bucket' => $this->agingBucket($daysOverdue),
            ];
        })->sortByDesc('days_overdue')->values();

        return [
            'as_of' => $asOf->toDateString(),
            'rows' => $rows->all(),
            'buckets' => [
                'current' => round($rows->where('bucket', 'current')->sum('outstanding'), 2),
                '1_30' => round($rows->where('bucket', '1_30')->sum('outstanding'), 2),
                '31_60' => round($rows->where('bucket', '31_60')->sum('outstanding'), 2),
                '61_90' => round($rows->where('bucket', '61_90')->sum('outstanding'), 2),
                'over_90' => round($rows->where('bucket', 'over_90')->sum('outstanding'), 2),
                'total' => round($rows->sum('outstanding'), 2),
            ],
        ];
    }

    public function apAging(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $asOf = $period['end_date'];
        $companyId = auth('admin')->user()->company_id;

        $bills = Bill::with(['party', 'billItems'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->get();

        $billIds = $bills->pluck('id');
        $billMorphType = (new Bill)->getMorphClass();
        $paidByBill = DB::table('payment_allocations')
            ->where('payable_type', $billMorphType)
            ->whereIn('payable_id', $billIds)
            ->whereNull('deleted_at')
            ->groupBy('payable_id')
            ->selectRaw('payable_id, COALESCE(SUM(amount), 0) as paid')
            ->pluck('paid', 'payable_id');

        $bills = $bills->filter(function (Bill $bill) use ($paidByBill) {
            $paid = (float) ($paidByBill->get($bill->id, 0));
            $total = $bill->billItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);

            return round($total - $paid, 2) > 0;
        });

        $rows = $bills->map(function (Bill $bill) use ($asOf, $paidByBill) {
            $paid = (float) ($paidByBill->get($bill->id, 0));
            $total = $bill->billItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);
            $outstanding = round($total - $paid, 2);
            $dueDate = $bill->due_date ? Carbon::parse($bill->due_date) : Carbon::parse($bill->bill_date)->addDays(30);
            $daysOverdue = max(0, $asOf->diffInDays($dueDate, false) * -1);

            return [
                'bill_no' => $bill->bill_no,
                'party_name' => $bill->party?->name ?? '-',
                'bill_date' => $bill->bill_date,
                'due_date' => $dueDate->toDateString(),
                'days_overdue' => $daysOverdue,
                'outstanding' => $outstanding,
                'bucket' => $this->agingBucket($daysOverdue),
            ];
        })->sortByDesc('days_overdue')->values();

        return [
            'as_of' => $asOf->toDateString(),
            'rows' => $rows->all(),
            'buckets' => [
                'current' => round($rows->where('bucket', 'current')->sum('outstanding'), 2),
                '1_30' => round($rows->where('bucket', '1_30')->sum('outstanding'), 2),
                '31_60' => round($rows->where('bucket', '31_60')->sum('outstanding'), 2),
                '61_90' => round($rows->where('bucket', '61_90')->sum('outstanding'), 2),
                'over_90' => round($rows->where('bucket', 'over_90')->sum('outstanding'), 2),
                'total' => round($rows->sum('outstanding'), 2),
            ],
        ];
    }

    public function inventoryValuation(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $stocks = DB::table('stocks')
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.company_id', $companyId)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                'product_categories.name as category_name',
                'product_variants.id as variant_id',
                'product_variants.sku as sku',
                'warehouses.name as warehouse_name',
                'stocks.quantity',
            ])
            ->get();

        $layerCosts = DB::table('stock_layers')
            ->where('company_id', $companyId)
            ->where('qty_remaining', '>', 0)
            ->select('product_variant_id', 'warehouse_id')
            ->selectRaw('SUM(qty_remaining) as qty, SUM(qty_remaining * unit_cost) as total_cost')
            ->groupBy('product_variant_id', 'warehouse_id')
            ->get()
            ->keyBy(fn ($row) => $row->product_variant_id.'_'.$row->warehouse_id);

        $rows = $stocks->map(function ($stock) use ($layerCosts) {
            $key = $stock->variant_id.'_'.($stock->warehouse_id ?? '');
            $layer = $layerCosts->get($key);
            $unitCost = ($layer && $layer->qty > 0) ? round($layer->total_cost / $layer->qty, 4) : 0;
            $totalValue = round($stock->quantity * $unitCost, 2);

            return [
                'product_name' => $stock->product_name,
                'product_code' => $stock->product_code,
                'sku' => $stock->sku,
                'category' => $stock->category_name,
                'warehouse' => $stock->warehouse_name,
                'quantity' => (float) $stock->quantity,
                'unit_cost' => $unitCost,
                'total_value' => $totalValue,
            ];
        })->values();

        return [
            'rows' => $rows->all(),
            'summary' => [
                'total_items' => $rows->count(),
                'total_quantity' => round($rows->sum('quantity'), 2),
                'total_value' => round($rows->sum('total_value'), 2),
            ],
        ];
    }

    public function stockAging(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;
        $asOf = now()->toDateString();

        $layers = DB::table('stock_layers')
            ->join('product_variants', 'product_variants.id', '=', 'stock_layers.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_layers.warehouse_id')
            ->where('stock_layers.company_id', $companyId)
            ->where('stock_layers.qty_remaining', '>', 0)
            ->select([
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
                'stock_layers.qty_remaining',
                'stock_layers.unit_cost',
                'stock_layers.created_at',
            ])
            ->get();

        $rows = $layers->map(function ($layer) use ($asOf) {
            $ageInDays = Carbon::parse($layer->created_at)->diffInDays($asOf);
            $totalValue = round($layer->qty_remaining * $layer->unit_cost, 2);

            return [
                'product_name' => $layer->product_name,
                'product_code' => $layer->product_code,
                'sku' => $layer->sku,
                'warehouse' => $layer->warehouse_name,
                'quantity' => (float) $layer->qty_remaining,
                'unit_cost' => (float) $layer->unit_cost,
                'total_value' => $totalValue,
                'age_days' => $ageInDays,
                'age_bucket' => $this->stockAgeBucket($ageInDays),
            ];
        })->values();

        return [
            'as_of' => $asOf,
            'rows' => $rows->all(),
            'buckets' => [
                'under_30' => round($rows->where('age_bucket', 'under_30')->sum('total_value'), 2),
                '31_90' => round($rows->where('age_bucket', '31_90')->sum('total_value'), 2),
                '91_180' => round($rows->where('age_bucket', '91_180')->sum('total_value'), 2),
                'over_180' => round($rows->where('age_bucket', 'over_180')->sum('total_value'), 2),
                'total' => round($rows->sum('total_value'), 2),
            ],
        ];
    }

    public function reorderAlerts(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $alerts = DB::table('stocks')
            ->join('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.company_id', $companyId)
            ->whereColumn('stocks.quantity', '<=', 'products.min_stock_level')
            ->where('products.min_stock_level', '>', 0)
            ->select([
                'products.id as product_id',
                'products.name as product_name',
                'products.code as product_code',
                'products.min_stock_level',
                'products.reorder_quantity',
                'product_variants.id as variant_id',
                'product_variants.sku',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'stocks.quantity as current_stock',
            ])
            ->get();

        return [
            'rows' => $alerts->values()->all(),
            'count' => $alerts->count(),
        ];
    }

    public function tdsReport(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $companyId = auth('admin')->user()->company_id;

        // Filter by journal date when available (accurate accounting date);
        // fall back to created_at for deductions without a linked journal.
        $deductions = DB::table('tds_deductions')
            ->leftJoin('parties', 'parties.id', '=', 'tds_deductions.party_id')
            ->leftJoin('journals', 'journals.id', '=', 'tds_deductions.journal_id')
            ->where('tds_deductions.company_id', $companyId)
            ->whereBetween(
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at))'),
                [$period['start_date']->toDateString(), $period['end_date']->toDateString()]
            )
            ->select([
                DB::raw("COALESCE(parties.name, 'N/A') as party_name"),
                DB::raw("COALESCE(parties.pan, '') as party_pan"),
                'tds_deductions.tds_category',
                'tds_deductions.base_amount',
                'tds_deductions.tds_rate',
                'tds_deductions.tds_amount',
                'tds_deductions.period_month',
                DB::raw('COALESCE(journals.date, DATE(tds_deductions.created_at)) as effective_date'),
            ])
            ->orderBy('effective_date')
            ->get();

        return [
            'period' => [
                'start_date' => $period['start_date']->toDateString(),
                'end_date' => $period['end_date']->toDateString(),
                'label' => sprintf('For the period %s to %s', $period['start_date']->format('d-m-Y'), $period['end_date']->format('d-m-Y')),
            ],
            'fiscal_year' => $this->mapFiscalYear($period['fiscal_year']),
            'rows' => $deductions->values()->all(),
            'summary' => [
                'total_base_amount' => round($deductions->sum('base_amount'), 2),
                'total_tds_amount' => round($deductions->sum('tds_amount'), 2),
            ],
        ];
    }

    /**
     * GL integrity report: approved, non-voided sales invoices and purchase
     * bills that have no general-ledger journal. Surfaces documents that were
     * approved while control accounts were unconfigured (legacy data) or that
     * otherwise slipped past posting, so they can be re-posted.
     *
     * @return array{
     *     sales_invoices: list<array{id:int, document_no:string, date:string, party:?string, amount:float}>,
     *     purchase_bills: list<array{id:int, document_no:string, date:string, party:?string, amount:float}>,
     *     summary: array{sales_count:int, sales_amount:float, purchase_count:int, purchase_amount:float, total_count:int}
     * }
     */
    public function unpostedDocuments(Request $request): array
    {
        $invoiceMorph = (new Invoice)->getMorphClass();
        $billMorph = (new Bill)->getMorphClass();
        $expenseMorph = (new Expense)->getMorphClass();
        $creditNoteMorph = (new CreditNote)->getMorphClass();
        $debitNoteMorph = (new DebitNote)->getMorphClass();

        $invoices = Invoice::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereNotExists(function ($query) use ($invoiceMorph) {
                $query->select(DB::raw(1))
                    ->from('journals')
                    ->whereColumn('journals.reference_id', 'invoices.id')
                    ->where('journals.reference_type', $invoiceMorph)
                    ->whereNull('journals.deleted_at');
            })
            ->with(['party:id,name', 'invoiceItems', 'discount'])
            ->latest('invoice_date')
            ->get();

        $bills = Bill::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereNotExists(function ($query) use ($billMorph) {
                $query->select(DB::raw(1))
                    ->from('journals')
                    ->whereColumn('journals.reference_id', 'bills.id')
                    ->where('journals.reference_type', $billMorph)
                    ->whereNull('journals.deleted_at');
            })
            ->with(['party:id,name', 'billItems'])
            ->latest('bill_date')
            ->get();

        // An approved, non-voided expense without a journal is unposted.
        $expenses = Expense::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereNotExists(function ($query) use ($expenseMorph) {
                $query->select(DB::raw(1))
                    ->from('journals')
                    ->whereColumn('journals.reference_id', 'expenses.id')
                    ->where('journals.reference_type', $expenseMorph)
                    ->whereNull('journals.deleted_at');
            })
            ->with(['party:id,name', 'expenseItems'])
            ->latest('date')
            ->get();

        $creditNotes = CreditNote::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereNotExists(function ($query) use ($creditNoteMorph) {
                $query->select(DB::raw(1))
                    ->from('journals')
                    ->whereColumn('journals.reference_id', 'credit_notes.id')
                    ->where('journals.reference_type', $creditNoteMorph)
                    ->whereNull('journals.deleted_at');
            })
            ->with(['party:id,name', 'creditNoteItems'])
            ->latest('credit_note_date')
            ->get();

        $debitNotes = DebitNote::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereNotExists(function ($query) use ($debitNoteMorph) {
                $query->select(DB::raw(1))
                    ->from('journals')
                    ->whereColumn('journals.reference_id', 'debit_notes.id')
                    ->where('journals.reference_type', $debitNoteMorph)
                    ->whereNull('journals.deleted_at');
            })
            ->with(['party:id,name', 'debitNoteItems'])
            ->latest('debit_note_date')
            ->get();

        $salesRows = $invoices->map(fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'document_no' => (string) $invoice->invoice_no,
            'date' => $this->toDateString($invoice->invoice_date),
            'party' => $invoice->party?->name,
            'amount' => $this->documentLineTotal($invoice->invoiceItems, (float) ($invoice->discount?->amount ?? 0)),
        ])->values()->all();

        $purchaseRows = $bills->map(fn (Bill $bill) => [
            'id' => $bill->id,
            'document_no' => (string) $bill->bill_no,
            'date' => $this->toDateString($bill->bill_date),
            'party' => $bill->party?->name,
            'amount' => $this->documentLineTotal($bill->billItems, 0.0),
        ])->values()->all();

        $expenseRows = $expenses->map(fn (Expense $expense) => [
            'id' => $expense->id,
            'document_no' => (string) $expense->expense_no,
            'date' => $this->toDateString($expense->date),
            'party' => $expense->party?->name,
            'amount' => $this->expenseTotal($expense->expenseItems),
        ])->values()->all();

        $creditNoteRows = $creditNotes->map(fn (CreditNote $note) => [
            'id' => $note->id,
            'document_no' => (string) $note->credit_note_no,
            'date' => $this->toDateString($note->credit_note_date),
            'party' => $note->party?->name,
            'amount' => $this->documentLineTotal($note->creditNoteItems, 0.0),
        ])->values()->all();

        $debitNoteRows = $debitNotes->map(fn (DebitNote $note) => [
            'id' => $note->id,
            'document_no' => (string) $note->debit_note_no,
            'date' => $this->toDateString($note->debit_note_date),
            'party' => $note->party?->name,
            'amount' => $this->documentLineTotal($note->debitNoteItems, 0.0),
        ])->values()->all();

        return [
            'sales_invoices' => $salesRows,
            'purchase_bills' => $purchaseRows,
            'expenses' => $expenseRows,
            'credit_notes' => $creditNoteRows,
            'debit_notes' => $debitNoteRows,
            'summary' => [
                'sales_count' => count($salesRows),
                'sales_amount' => round(array_sum(array_column($salesRows, 'amount')), 2),
                'purchase_count' => count($purchaseRows),
                'purchase_amount' => round(array_sum(array_column($purchaseRows, 'amount')), 2),
                'expense_count' => count($expenseRows),
                'expense_amount' => round(array_sum(array_column($expenseRows, 'amount')), 2),
                'credit_note_count' => count($creditNoteRows),
                'credit_note_amount' => round(array_sum(array_column($creditNoteRows, 'amount')), 2),
                'debit_note_count' => count($debitNoteRows),
                'debit_note_amount' => round(array_sum(array_column($debitNoteRows, 'amount')), 2),
                'total_count' => count($salesRows) + count($purchaseRows) + count($expenseRows)
                    + count($creditNoteRows) + count($debitNoteRows),
            ],
        ];
    }

    /**
     * GL integrity report: every approved, non-deleted journal whose line-item
     * DR/CR totals don't balance (above a 1-paisa rounding tolerance). New
     * postings can't produce imbalances — `JournalBalanceGuard` enforces it at
     * write-time — but this surfaces any legacy or manually-edited data.
     *
     * @return array{
     *     rows: list<array{id:int, voucher_no:?string, type:?string, date:?string, reference_type:?string, reference_id:?int, dr_total:float, cr_total:float, delta:float}>,
     *     summary: array{count:int, total_delta:float}
     * }
     */
    public function unbalancedJournals(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        // Pull totals per journal then filter in PHP — portable across MySQL
        // and SQLite without having-clause-on-alias differences, and the result
        // set is small (only journals with items, scoped to one company).
        $records = DB::table('journals')
            ->leftJoin('journal_items', function ($join) {
                $join->on('journal_items.journal_id', '=', 'journals.id')
                    ->whereNull('journal_items.deleted_at');
            })
            ->where('journals.company_id', $companyId)
            ->whereNull('journals.deleted_at')
            ->groupBy(
                'journals.id', 'journals.voucher_no', 'journals.type',
                'journals.date', 'journals.reference_type', 'journals.reference_id',
            )
            ->selectRaw('
                journals.id,
                journals.voucher_no,
                journals.type,
                journals.date,
                journals.reference_type,
                journals.reference_id,
                COALESCE(SUM(journal_items.dr_amount), 0) AS dr_total,
                COALESCE(SUM(journal_items.cr_amount), 0) AS cr_total
            ')
            ->orderByDesc('journals.date')
            ->get();

        $rows = $records
            ->filter(fn ($row) => JournalBalanceGuard::exceedsTolerance((float) $row->dr_total - (float) $row->cr_total))
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'voucher_no' => $row->voucher_no,
                'type' => $row->type,
                'date' => $row->date,
                'reference_type' => $row->reference_type,
                'reference_id' => $row->reference_id ? (int) $row->reference_id : null,
                'dr_total' => round((float) $row->dr_total, 2),
                'cr_total' => round((float) $row->cr_total, 2),
                'delta' => round((float) $row->dr_total - (float) $row->cr_total, 2),
            ])
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'summary' => [
                'count' => count($rows),
                'total_delta' => round(array_sum(array_map(fn ($r) => abs($r['delta']), $rows)), 2),
            ],
        ];
    }

    /**
     * AR / AP control-account reconciliation: compare the sub-ledger total
     * (sum of outstanding from approved unvoided invoices/bills minus
     * receipts/payments) against the GL control-account balance. They must
     * match — a non-zero delta points at a missing or extra GL posting, a
     * manual journal voucher hitting the control account, or an unposted
     * approved document.
     *
     * @return array{
     *     ar: array{account_id:?int, account_configured:bool, gl_balance:float, subledger_balance:float, delta:float, reconciled:bool},
     *     ap: array{account_id:?int, account_configured:bool, gl_balance:float, subledger_balance:float, delta:float, reconciled:bool}
     * }
     */
    public function arApReconciliation(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        // Bypass tenant scope but keep soft-delete filtering — we want the
        // currently-active settings row, not a previously deleted one.
        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        return [
            'ar' => $this->reconcileControlAccount(
                $companyId,
                $settings?->customer_account_id,
                $this->arSubledgerOutstanding($companyId),
                glSign: 1, // AR is a DR-side asset; subledger sign matches (DR - CR).
            ),
            'ap' => $this->reconcileControlAccount(
                $companyId,
                $settings?->supplier_account_id,
                $this->apSubledgerOutstanding($companyId),
                glSign: -1, // AP is a CR-side liability; flip sign to get (CR - DR).
            ),
        ];
    }

    /**
     * Reconciles the live perpetual-inventory valuation (sum of remaining stock
     * layers × unit cost) against the GL inventory control account. They should
     * match when every inventory-valued stock movement has posted its journal;
     * a non-zero delta surfaces unposted movements, manual journals touching the
     * inventory account, or landed costs that hit the GL but not the layers.
     *
     * @return array{account_id:?int, account_configured:bool, gl_balance:float, subledger_balance:float, inventory_value:float, delta:float, reconciled:bool}
     */
    public function inventoryGlReconciliation(Request $request): array
    {
        $companyId = auth('admin')->user()->company_id;

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->first();

        $result = $this->reconcileControlAccount(
            $companyId,
            $settings?->inventory_account_id,
            $this->inventoryPerpetualValue($companyId),
            glSign: 1, // Inventory is a DR-side asset; (DR - CR) matches on-hand value.
        );

        // Alias the shared sub-ledger key under an inventory-specific name.
        $result['inventory_value'] = $result['subledger_balance'];

        return $result;
    }

    /**
     * @return array{account_id:?int, account_configured:bool, gl_balance:float, subledger_balance:float, delta:float, reconciled:bool}
     */
    private function reconcileControlAccount(int $companyId, ?int $accountId, float $subledger, int $glSign): array
    {
        if (! $accountId) {
            return [
                'account_id' => null,
                'account_configured' => false,
                'gl_balance' => 0.0,
                'subledger_balance' => round($subledger, 2),
                'delta' => round($subledger, 2),
                'reconciled' => false,
            ];
        }

        $sums = DB::table('journal_items')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journals.company_id', $companyId)
            ->whereNull('journals.deleted_at')
            ->whereNull('journal_items.deleted_at')
            ->where('journal_items.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(journal_items.dr_amount), 0) AS dr, COALESCE(SUM(journal_items.cr_amount), 0) AS cr')
            ->first();

        $glBalance = round($glSign * ((float) $sums->dr - (float) $sums->cr), 2);
        $delta = round($subledger - $glBalance, 2);

        return [
            'account_id' => $accountId,
            'account_configured' => true,
            'gl_balance' => $glBalance,
            'subledger_balance' => round($subledger, 2),
            'delta' => $delta,
            'reconciled' => ! JournalBalanceGuard::exceedsTolerance($delta),
        ];
    }

    /**
     * Sum of unpaid amounts on approved, non-voided invoices, less amounts
     * already settled by receipt allocations. Approximate (does not subtract
     * credit notes separately — they appear in the GL side, so a credit note
     * with no matching invoice adjustment will show as a delta to investigate).
     */
    private function arSubledgerOutstanding(int $companyId): float
    {
        $itemTotals = (float) (DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.company_id', $companyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_items.deleted_at')
            ->selectRaw('COALESCE(SUM((invoice_items.quantity * invoice_items.rate) - invoice_items.discount_amount + invoice_items.tax_amount), 0) as total')
            ->value('total') ?? 0);

        $orderDiscounts = (float) (DB::table('discounts')
            ->join('invoices', 'discounts.discountable_id', '=', 'invoices.id')
            ->where('discounts.discountable_type', Invoice::class)
            ->where('invoices.company_id', $companyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->selectRaw('COALESCE(SUM(discounts.amount), 0) as total')
            ->value('total') ?? 0);

        $invoiceTotal = $itemTotals - $orderDiscounts;

        $receiptAllocations = (float) DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.company_id', $companyId)
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->sum('receipt_allocations.amount');

        return $invoiceTotal - $receiptAllocations;
    }

    /**
     * Symmetric of arSubledgerOutstanding for bills and payment allocations.
     */
    private function apSubledgerOutstanding(int $companyId): float
    {
        $billTotal = (float) (DB::table('bill_items')
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->where('bills.company_id', $companyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereNull('bill_items.deleted_at')
            ->selectRaw('COALESCE(SUM((bill_items.quantity * bill_items.rate) - bill_items.discount_amount + bill_items.tax_amount), 0) as total')
            ->value('total') ?? 0);

        $paymentAllocations = (float) DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.company_id', $companyId)
            ->where('payments.status', StatusEnum::APPROVED->value)
            ->whereNull('payments.deleted_at')
            ->whereNull('payment_allocations.deleted_at')
            ->sum('payment_allocations.amount');

        return $billTotal - $paymentAllocations;
    }

    /**
     * Live perpetual-inventory valuation: sum of remaining stock-layer quantities
     * × their unit cost across all warehouses for the company. This is the same
     * cost basis the StockMovementGlPostingService posts (movement total_cost),
     * so it should equal the GL inventory control account when fully posted.
     */
    private function inventoryPerpetualValue(int $companyId): float
    {
        return (float) DB::table('stock_layers')
            ->where('company_id', $companyId)
            ->where('qty_remaining', '>', 0)
            ->selectRaw('COALESCE(SUM(qty_remaining * unit_cost), 0) AS value')
            ->value('value');
    }

    /**
     * Net expense total from its line items: sum(amount - discount + tax).
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $items
     */
    private function expenseTotal(Collection $items): float
    {
        return round($items->sum(fn ($item) => (float) $item->amount
            - (float) $item->discount_amount
            + (float) $item->tax_amount), 2);
    }

    /**
     * Net document total from its line items, less any order-level discount:
     * sum(quantity * rate - line discount + tax) - order discount.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $items
     */
    private function documentLineTotal(Collection $items, float $orderDiscount): float
    {
        $lineTotal = $items->sum(fn ($item) => ((float) $item->quantity * (float) $item->rate)
            - (float) $item->discount_amount
            + (float) $item->tax_amount);

        return round($lineTotal - $orderDiscount, 2);
    }

    private function toDateString($date): string
    {
        if ($date instanceof \Carbon\Carbon) {
            return $date->toDateString();
        }

        return (string) $date;
    }

    private function agingBucket(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        }
        if ($daysOverdue <= 30) {
            return '1_30';
        }
        if ($daysOverdue <= 60) {
            return '31_60';
        }
        if ($daysOverdue <= 90) {
            return '61_90';
        }

        return 'over_90';
    }

    private function stockAgeBucket(int $days): string
    {
        if ($days <= 30) {
            return 'under_30';
        }
        if ($days <= 90) {
            return '31_90';
        }
        if ($days <= 180) {
            return '91_180';
        }

        return 'over_180';
    }

    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'compare_fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
        ]);

        $company = auth('admin')->user()?->company?->loadMissing('fiscalYear');

        $fiscalYear = ! empty($validated['fiscal_year_id'])
            ? FiscalYear::find($validated['fiscal_year_id'])
            : $company?->fiscalYear;

        $startDate = ! empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : ($fiscalYear?->start_date?->copy()->startOfDay() ?? now()->startOfMonth()->startOfDay());

        $endDate = ! empty($validated['end_date'])
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : ($fiscalYear?->end_date?->copy()->endOfDay() ?? now()->endOfDay());

        if ($startDate->gt($endDate)) {
            abort(422, 'Start date must be before or equal to end date.');
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'fiscal_year' => $fiscalYear,
            'validated' => $validated,
        ];
    }

    private function resolveCompareFiscalYear(Request $request, ?FiscalYear $currentFiscalYear): ?FiscalYear
    {
        $compareFiscalYearId = $request->input('compare_fiscal_year_id');

        if (empty($compareFiscalYearId)) {
            return null;
        }

        $compareFiscalYear = FiscalYear::find($compareFiscalYearId);

        if (! $compareFiscalYear) {
            return null;
        }

        if ($currentFiscalYear && $compareFiscalYear->id === $currentFiscalYear->id) {
            return null;
        }

        return $compareFiscalYear;
    }

    private function loadSortedRootGroups(): Collection
    {
        $groups = AccountGroup::with([
            'accounts',
            'childrenRecursive',
        ])
            ->whereNull('parent_id')
            ->get();

        return $this->sortGroupTree($groups);
    }

    private function sortGroupTree(Collection $groups): Collection
    {
        return $groups
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (AccountGroup $group) {
                $group->setRelation(
                    'childrenRecursive',
                    $this->sortGroupTree(collect($group->childrenRecursive))
                );

                $group->setRelation(
                    'accounts',
                    collect($group->accounts)
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->values()
                );

                return $group;
            });
    }

    private function collectAccountIds(Collection $groups): array
    {
        $accountIds = [];

        foreach ($groups as $group) {
            foreach (collect($group->accounts) as $account) {
                $accountIds[] = $account->id;
            }

            $children = collect($group->childrenRecursive);
            if ($children->isNotEmpty()) {
                $accountIds = array_merge($accountIds, $this->collectAccountIds($children));
            }
        }

        return array_values(array_unique($accountIds));
    }

    private function fetchBalancesForPeriod(Collection $groups, Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->fetchTrialBalanceAmounts(
            $this->collectAccountIds($groups),
            $startDate,
            $endDate
        );
    }

    private function fetchTrialBalanceAmounts(array $accountIds, Carbon $startDate, Carbon $endDate): Collection
    {
        if (empty($accountIds)) {
            return collect();
        }

        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw(
                'SUM(CASE WHEN journals.date < ? THEN journal_items.dr_amount ELSE 0 END) as opening_dr',
                [$startDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date < ? THEN journal_items.cr_amount ELSE 0 END) as opening_cr',
                [$startDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date BETWEEN ? AND ? THEN journal_items.dr_amount ELSE 0 END) as transaction_dr',
                [$startDate->toDateString(), $endDate->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN journals.date BETWEEN ? AND ? THEN journal_items.cr_amount ELSE 0 END) as transaction_cr',
                [$startDate->toDateString(), $endDate->toDateString()]
            )
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '<=', $endDate->toDateString())
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id')
            ->map(function ($row) {
                return [
                    'opening' => [
                        'dr' => (float) $row->opening_dr,
                        'cr' => (float) $row->opening_cr,
                    ],
                    'transaction' => [
                        'dr' => (float) $row->transaction_dr,
                        'cr' => (float) $row->transaction_cr,
                    ],
                ];
            });
    }

    private function fetchNetAmountsForDate(Collection $groups, Carbon $endDate): Collection
    {
        $accountIds = $this->collectAccountIds($groups);

        if (empty($accountIds)) {
            return collect();
        }

        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.dr_amount - journal_items.cr_amount) as net_amount')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '<=', $endDate->toDateString())
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id')
            ->map(fn ($row) => round((float) $row->net_amount, 2));
    }

    private function fetchPeriodNetAmounts(Collection $groups, Carbon $startDate, Carbon $endDate): Collection
    {
        $accountIds = $this->collectAccountIds($groups);

        if (empty($accountIds)) {
            return collect();
        }

        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.dr_amount - journal_items.cr_amount) as net_amount')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereBetween('journals.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id')
            ->map(fn ($row) => round((float) $row->net_amount, 2));
    }

    private function fetchNetAmountsForFiscalYear(Collection $groups, FiscalYear $fiscalYear): Collection
    {
        $accountIds = $this->collectAccountIds($groups);

        if (empty($accountIds)) {
            return collect();
        }

        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.dr_amount - journal_items.cr_amount) as net_amount')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->where('journals.fiscal_year_id', $fiscalYear->id)
            ->whereNull('journals.deleted_at')
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id')
            ->map(fn ($row) => round((float) $row->net_amount, 2));
    }

    private function fetchLedgerOpeningBalance(int $accountId, Carbon $startDate): float
    {
        $companyId = auth('admin')->user()->company_id;

        $openingBalance = JournalItem::query()
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->where('journal_items.account_id', $accountId)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereDate('journals.date', '<', $startDate->toDateString())
            ->selectRaw('SUM(journal_items.cr_amount - journal_items.dr_amount) as opening_balance')
            ->value('opening_balance');

        return round((float) ($openingBalance ?? 0), 2);
    }

    private function fetchLedgerEntries(int $accountId, Carbon $startDate, Carbon $endDate): Collection
    {
        $companyId = auth('admin')->user()->company_id;

        return JournalItem::query()
            ->with('journal')
            ->where('account_id', $accountId)
            ->whereHas('journal', function ($query) use ($companyId, $startDate, $endDate) {
                $query->where('company_id', $companyId)
                    ->where('status', StatusEnum::APPROVED->value)
                    ->whereNull('deleted_at')
                    ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
            })
            ->get()
            ->sortBy([
                fn ($item) => $item->journal?->date?->timestamp ?? 0,
                fn ($item) => $item->journal?->voucher_no ?? '',
                fn ($item) => $item->id,
            ])
            ->values();
    }

    private function transformTrialBalanceGroup(AccountGroup $group, Collection $balances): array
    {
        $children = [];

        foreach (collect($group->childrenRecursive) as $childGroup) {
            $children[] = $this->transformTrialBalanceGroup($childGroup, $balances);
        }

        foreach (collect($group->accounts) as $account) {
            $children[] = $this->transformTrialBalanceAccount($account, $balances);
        }

        $totals = $this->aggregateRows($children);

        return [
            'key' => 'group-'.$group->id,
            'type' => 'group',
            'id' => $group->id,
            'name' => $group->name,
            'code' => $group->code,
            'label' => $group->name,
            'opening' => $totals['opening'],
            'transaction' => $totals['transaction'],
            'closing' => $totals['closing'],
            'children' => $children,
        ];
    }

    private function transformTrialBalanceAccount($account, Collection $balances): array
    {
        $amounts = $balances->get($account->id, [
            'opening' => ['dr' => 0, 'cr' => 0],
            'transaction' => ['dr' => 0, 'cr' => 0],
        ]);

        return [
            'key' => 'account-'.$account->id,
            'type' => 'account',
            'id' => $account->id,
            'name' => $account->name,
            'code' => $account->code,
            'label' => trim(sprintf('%s (%s)', $account->name, $account->code)),
            'opening' => $amounts['opening'],
            'transaction' => $amounts['transaction'],
            'closing' => $this->closingColumns(
                (float) $amounts['opening']['dr'] + (float) $amounts['transaction']['dr'],
                (float) $amounts['opening']['cr'] + (float) $amounts['transaction']['cr']
            ),
            'children' => [],
        ];
    }

    private function transformBalanceSheetGroup(AccountGroup $group, Collection $currentAmounts, Collection $previousAmounts): array
    {
        return $this->transformAmountTreeGroup($group, $currentAmounts, $previousAmounts);
    }

    private function transformAmountTreeGroup(AccountGroup $group, Collection $currentAmounts, Collection $previousAmounts): array
    {
        $children = [];

        foreach (collect($group->childrenRecursive) as $childGroup) {
            $children[] = $this->transformAmountTreeGroup($childGroup, $currentAmounts, $previousAmounts);
        }

        foreach (collect($group->accounts) as $account) {
            $children[] = $this->transformAmountTreeAccount($account, $currentAmounts, $previousAmounts);
        }

        return [
            'key' => 'group-'.$group->id,
            'type' => 'group',
            'id' => $group->id,
            'name' => $group->name,
            'code' => $group->code,
            'label' => $group->name,
            'amount' => round(collect($children)->sum('amount'), 2),
            'prev_amount' => round(collect($children)->sum('prev_amount'), 2),
            'children' => $children,
        ];
    }

    private function transformBalanceSheetAccount($account, Collection $currentAmounts, Collection $previousAmounts): array
    {
        return $this->transformAmountTreeAccount($account, $currentAmounts, $previousAmounts);
    }

    private function transformAmountTreeAccount($account, Collection $currentAmounts, Collection $previousAmounts): array
    {
        return [
            'key' => 'account-'.$account->id,
            'type' => 'account',
            'id' => $account->id,
            'name' => $account->name,
            'code' => $account->code,
            'label' => $account->name,
            'amount' => round((float) $currentAmounts->get($account->id, 0), 2),
            'prev_amount' => round((float) $previousAmounts->get($account->id, 0), 2),
            'children' => [],
        ];
    }

    private function aggregateRows(array $rows): array
    {
        $openingDr = 0;
        $openingCr = 0;
        $transactionDr = 0;
        $transactionCr = 0;
        $closingDr = 0;
        $closingCr = 0;

        foreach ($rows as $row) {
            $openingDr += (float) data_get($row, 'opening.dr', 0);
            $openingCr += (float) data_get($row, 'opening.cr', 0);
            $transactionDr += (float) data_get($row, 'transaction.dr', 0);
            $transactionCr += (float) data_get($row, 'transaction.cr', 0);
            $closingDr += (float) data_get($row, 'closing.dr', 0);
            $closingCr += (float) data_get($row, 'closing.cr', 0);
        }

        return [
            'opening' => [
                'dr' => round($openingDr, 2),
                'cr' => round($openingCr, 2),
            ],
            'transaction' => [
                'dr' => round($transactionDr, 2),
                'cr' => round($transactionCr, 2),
            ],
            'closing' => [
                'dr' => round($closingDr, 2),
                'cr' => round($closingCr, 2),
            ],
        ];
    }

    private function summarizeTrialBalanceRows(array $rows): array
    {
        return $this->aggregateRows($rows);
    }

    private function closingColumns(float $drAmount, float $crAmount): array
    {
        $difference = round($drAmount - $crAmount, 2);

        if ($difference > 0) {
            return [
                'dr' => $difference,
                'cr' => 0,
            ];
        }

        if ($difference < 0) {
            return [
                'dr' => 0,
                'cr' => abs($difference),
            ];
        }

        return [
            'dr' => 0,
            'cr' => 0,
        ];
    }

    private function mapFiscalYear(?FiscalYear $fiscalYear): ?array
    {
        if (! $fiscalYear) {
            return null;
        }

        return [
            'id' => $fiscalYear->id,
            'year_name' => $fiscalYear->year_name,
            'year_code' => $fiscalYear->year_code,
            'start_date' => $fiscalYear->start_date?->toDateString(),
            'end_date' => $fiscalYear->end_date?->toDateString(),
        ];
    }
}
