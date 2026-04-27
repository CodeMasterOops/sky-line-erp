<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use App\Models\Bill;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
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

        $invoices = Invoice::with(['party', 'invoiceItems.tax'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereBetween('invoice_date', [$period['start_date']->toDateString(), $period['end_date']->toDateString()])
            ->orderBy('invoice_date')
            ->orderBy('invoice_no')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) {
            $taxableAmount = $invoice->invoiceItems
                ->where('tax_line_type', 'taxable')
                ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

            $vatAmount = $invoice->invoiceItems
                ->where('tax_line_type', 'taxable')
                ->sum('tax_amount');

            $exemptAmount = $invoice->invoiceItems
                ->where('tax_line_type', 'exempt')
                ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

            $zeroRatedAmount = $invoice->invoiceItems
                ->where('tax_line_type', 'zero_rated')
                ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

            return [
                'date' => $invoice->invoice_date,
                'bijak_no' => $invoice->bijak_no ?: $invoice->invoice_no,
                'buyer_name' => $invoice->party?->name ?? '-',
                'buyer_pan' => $invoice->party?->pan ?? '-',
                'taxable_amount' => round((float) $taxableAmount, 2),
                'vat_amount' => round((float) $vatAmount, 2),
                'exempt_amount' => round((float) $exemptAmount, 2),
                'zero_rated_amount' => round((float) $zeroRatedAmount, 2),
                'total_amount' => round((float) ($taxableAmount + $vatAmount + $exemptAmount + $zeroRatedAmount), 2),
            ];
        })->values()->all();

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

        $bills = Bill::with(['party', 'billItems.tax'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereBetween('bill_date', [$period['start_date']->toDateString(), $period['end_date']->toDateString()])
            ->orderBy('bill_date')
            ->orderBy('bill_no')
            ->get();

        $rows = $bills->map(function (Bill $bill) {
            $taxableAmount = $bill->billItems
                ->where('tax_line_type', 'taxable')
                ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

            $inputVat = $bill->billItems
                ->where('tax_line_type', 'taxable')
                ->sum('tax_amount');

            $exemptAmount = $bill->billItems
                ->where('tax_line_type', 'exempt')
                ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount);

            return [
                'date' => $bill->bill_date,
                'bill_no' => $bill->bill_no,
                'supplier_name' => $bill->party?->name ?? '-',
                'supplier_pan' => $bill->party?->pan ?? '-',
                'taxable_amount' => round((float) $taxableAmount, 2),
                'input_vat' => round((float) $inputVat, 2),
                'exempt_amount' => round((float) $exemptAmount, 2),
                'total_amount' => round((float) ($taxableAmount + $inputVat + $exemptAmount), 2),
            ];
        })->values()->all();

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

    public function cashFlow(Request $request): array
    {
        $period = $this->resolvePeriod($request);
        $companyId = auth('admin')->user()->company_id;

        $accountGroups = AccountGroup::with(['accounts', 'childrenRecursive'])
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->get();

        $getAccountIds = function (Collection $groups) use (&$getAccountIds) {
            $ids = [];
            foreach ($groups as $group) {
                $lowerName = strtolower($group->name);
                foreach ($group->accounts as $account) {
                    $ids[$account->id] = $lowerName;
                }
                if ($group->childrenRecursive->isNotEmpty()) {
                    $ids = $ids + $getAccountIds($group->childrenRecursive);
                }
            }

            return $ids;
        };

        $accountGroupMap = $getAccountIds(collect($accountGroups));

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
            $groupName = $accountGroupMap[$accountId] ?? '';
            $net = (float) $row->net;

            if (in_array($groupName, ['income', 'expenses'])) {
                $operating += $net;
            } elseif (in_array($groupName, ['assets'])) {
                $investing += -$net;
            } elseif (in_array($groupName, ['liabilities', 'equity'])) {
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
            ->get()
            ->filter(function (Invoice $invoice) {
                $paid = $invoice->receiptAllocations()->sum('allocated_amount');
                $total = $invoice->invoiceItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);

                return round($total - $paid, 2) > 0;
            });

        $rows = $invoices->map(function (Invoice $invoice) use ($asOf) {
            $paid = $invoice->receiptAllocations()->sum('allocated_amount');
            $total = $invoice->invoiceItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);
            $outstanding = round($total - $paid, 2);
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
            ->get()
            ->filter(function (Bill $bill) {
                $paid = $bill->paymentAllocations()->sum('allocated_amount');
                $total = $bill->billItems->sum(fn ($i) => ($i->quantity * $i->rate) + $i->tax_amount - $i->discount_amount);

                return round($total - $paid, 2) > 0;
            });

        $rows = $bills->map(function (Bill $bill) use ($asOf) {
            $paid = $bill->paymentAllocations()->sum('allocated_amount');
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
            ->where('remaining_qty', '>', 0)
            ->select('product_variant_id', 'warehouse_id')
            ->selectRaw('SUM(remaining_qty) as qty, SUM(remaining_qty * unit_cost) as total_cost')
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
            ->where('stock_layers.remaining_qty', '>', 0)
            ->select([
                'products.name as product_name',
                'products.code as product_code',
                'product_variants.sku',
                'warehouses.name as warehouse_name',
                'stock_layers.remaining_qty',
                'stock_layers.unit_cost',
                'stock_layers.created_at',
            ])
            ->get();

        $rows = $layers->map(function ($layer) use ($asOf) {
            $ageInDays = Carbon::parse($layer->created_at)->diffInDays($asOf);
            $totalValue = round($layer->remaining_qty * $layer->unit_cost, 2);

            return [
                'product_name' => $layer->product_name,
                'product_code' => $layer->product_code,
                'sku' => $layer->sku,
                'warehouse' => $layer->warehouse_name,
                'quantity' => (float) $layer->remaining_qty,
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
