<?php

namespace App\Services\Purchase;

use Carbon\Carbon;
use App\Models\Bill;
use App\Models\Party;
use App\Enums\StatusEnum;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Services\BranchScope;
use App\Services\TenantService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class PurchaseReportService
{
    public function purchaseSummary(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $row = DB::table('bills')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->selectRaw('
                COUNT(bills.id) as bill_count,
                COALESCE(SUM(it.subtotal), 0) as subtotal,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount,
                COALESCE(SUM(it.tax_amount), 0) as tax_amount
            ')
            ->first();

        $subtotal = (float) $row->subtotal;
        $totalDiscount = (float) $row->line_discount + (float) $row->order_discount;
        $taxAmount = (float) $row->tax_amount;
        $netPurchases = $subtotal - $totalDiscount + $taxAmount;

        $returnRow = DB::table('debit_notes')
            ->leftJoin('debit_note_items', function ($j) {
                $j->on('debit_notes.id', '=', 'debit_note_items.debit_note_id')
                    ->whereNull('debit_note_items.deleted_at');
            })
            ->where('debit_notes.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'debit_notes.branch_id'))
            ->where('debit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->whereBetween('debit_notes.debit_note_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('debit_notes.party_id', $request->party_id))
            ->selectRaw('
                COUNT(DISTINCT debit_notes.id) as return_count,
                COALESCE(SUM(debit_note_items.quantity * debit_note_items.rate) - SUM(debit_note_items.discount_amount) + SUM(debit_note_items.tax_amount), 0) as total_returns
            ')
            ->first();

        $totalReturns = round((float) ($returnRow->total_returns ?? 0), 2);

        return [
            'period' => $this->buildPeriod($request),
            'summary' => [
                'bill_count' => (int) $row->bill_count,
                'return_count' => (int) ($returnRow->return_count ?? 0),
                'subtotal' => round($subtotal, 2),
                'total_discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_purchases' => round($netPurchases, 2),
                'total_returns' => $totalReturns,
                'net_spend' => round($netPurchases - $totalReturns, 2),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function dailyPurchase(Request $request): array
    {
        $rows = $this->groupedBillQuery($request, 'DATE(bills.bill_date)', 'bill_date')->get();
        $mapped = $rows->map(fn ($row) => $this->mapAggregateRow($row, 'bill_date'))->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function monthlyPurchase(Request $request): array
    {
        $rows = $this->groupedBillQuery($request, $this->monthGroupExpr(), 'bill_month')->get();

        $mapped = $rows->map(function ($row) {
            $base = $this->mapAggregateRow($row, 'bill_month');
            $date = Carbon::parse($row->bill_month);
            $base['month_label'] = $date->format('M Y');
            $base['year'] = (int) $date->format('Y');
            $base['month'] = (int) $date->format('m');

            return $base;
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function yearlyPurchase(Request $request): array
    {
        $rows = $this->groupedBillQuery($request, $this->yearGroupExpr(), 'bill_year')->get();
        $mapped = $rows->map(fn ($row) => $this->mapAggregateRow($row, 'bill_year'))->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
        ];
    }

    public function supplierWisePurchase(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $rows = DB::table('bills')
            ->join('parties', 'parties.id', '=', 'bills.party_id')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->leftJoinSub($this->paidSubQuery($companyId), 'pt', fn ($j) => $j->on('bills.id', '=', 'pt.payable_id'))
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->selectRaw('
                bills.party_id,
                parties.name as party_name,
                parties.code as party_code,
                COUNT(bills.id) as bill_count,
                COALESCE(SUM(it.subtotal), 0) as subtotal,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount,
                COALESCE(SUM(it.tax_amount), 0) as tax_amount,
                COALESCE(SUM(pt.paid_total), 0) as paid_total
            ')
            ->groupBy('bills.party_id', 'parties.name', 'parties.code')
            ->orderByRaw('SUM(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0)) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $totalDiscount = (float) $row->line_discount + (float) $row->order_discount;
            $taxAmount = (float) $row->tax_amount;
            $netPurchases = round($subtotal - $totalDiscount + $taxAmount, 2);
            $paid = round((float) $row->paid_total, 2);

            return [
                'party_id' => $row->party_id,
                'party_name' => $row->party_name,
                'party_code' => $row->party_code ?? '',
                'bill_count' => (int) $row->bill_count,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_purchases' => $netPurchases,
                'paid' => $paid,
                'outstanding' => round(max($netPurchases - $paid, 0), 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'bill_count' => (int) $mapped->sum('bill_count'),
                'subtotal' => round((float) $mapped->sum('subtotal'), 2),
                'discount' => round((float) $mapped->sum('discount'), 2),
                'tax_amount' => round((float) $mapped->sum('tax_amount'), 2),
                'net_purchases' => round((float) $mapped->sum('net_purchases'), 2),
                'paid' => round((float) $mapped->sum('paid'), 2),
                'outstanding' => round((float) $mapped->sum('outstanding'), 2),
            ],
        ];
    }

    public function categoryWisePurchase(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $rows = DB::table('bill_items')
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->join('product_variants', 'product_variants.id', '=', 'bill_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereNull('bill_items.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->selectRaw("
                COALESCE(products.product_category_id, 0) as category_id,
                COALESCE(product_categories.name, 'Uncategorized') as category_name,
                COUNT(DISTINCT bills.id) as bill_count,
                COALESCE(SUM(bill_items.quantity), 0) as total_qty,
                COALESCE(SUM(bill_items.quantity * bill_items.rate), 0) as subtotal,
                COALESCE(SUM(bill_items.discount_amount), 0) as line_discount,
                COALESCE(SUM(bill_items.tax_amount), 0) as tax_amount
            ")
            ->groupBy('products.product_category_id', 'product_categories.name')
            ->orderByRaw('SUM(bill_items.quantity * bill_items.rate) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $discount = (float) $row->line_discount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'bill_count' => (int) $row->bill_count,
                'total_qty' => round((float) $row->total_qty, 2),
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_purchases' => round($subtotal - $discount + $taxAmount, 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'total_qty' => round((float) $mapped->sum('total_qty'), 2),
                'subtotal' => round((float) $mapped->sum('subtotal'), 2),
                'discount' => round((float) $mapped->sum('discount'), 2),
                'tax_amount' => round((float) $mapped->sum('tax_amount'), 2),
                'net_purchases' => round((float) $mapped->sum('net_purchases'), 2),
            ],
            'party_options' => $this->supplierOptions(),
            'category_options' => $this->categoryOptions(),
        ];
    }

    public function purchaseReturn(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $dnItemsSub = DB::table('debit_note_items')
            ->selectRaw('debit_note_id, COUNT(*) as item_count, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount, SUM(tax_amount) as tax_amount')
            ->whereNull('deleted_at')
            ->groupBy('debit_note_id');

        $paginator = DB::table('debit_notes')
            ->join('parties', 'parties.id', '=', 'debit_notes.party_id')
            ->leftJoin('bills as linked_bill', 'linked_bill.id', '=', 'debit_notes.bill_id')
            ->leftJoinSub($dnItemsSub, 'it', fn ($j) => $j->on('debit_notes.id', '=', 'it.debit_note_id'))
            ->where('debit_notes.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'debit_notes.branch_id'))
            ->where('debit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->whereBetween('debit_notes.debit_note_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('debit_notes.party_id', $request->party_id))
            ->select([
                'debit_notes.id',
                'debit_notes.debit_note_no',
                'debit_notes.debit_note_date',
                'debit_notes.party_id',
                'parties.name as party_name',
                'linked_bill.bill_no',
                DB::raw('COALESCE(it.item_count, 0) as item_count'),
                DB::raw('COALESCE(it.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(it.discount, 0) as discount'),
                DB::raw('COALESCE(it.tax_amount, 0) as tax_amount'),
            ])
            ->orderByDesc('debit_notes.debit_note_date')
            ->orderByDesc('debit_notes.id')
            ->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $discount = (float) $row->discount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'id' => $row->id,
                'debit_note_no' => $row->debit_note_no,
                'debit_note_date' => $row->debit_note_date,
                'party_name' => $row->party_name,
                'linked_bill_no' => $row->bill_no ?? '-',
                'item_count' => (int) $row->item_count,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($subtotal - $discount + $taxAmount, 2),
            ];
        })->values();

        $summaryRow = DB::table('debit_notes')
            ->leftJoin('debit_note_items', function ($j) {
                $j->on('debit_notes.id', '=', 'debit_note_items.debit_note_id')
                    ->whereNull('debit_note_items.deleted_at');
            })
            ->where('debit_notes.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'debit_notes.branch_id'))
            ->where('debit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->whereBetween('debit_notes.debit_note_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('debit_notes.party_id', $request->party_id))
            ->selectRaw('
                COUNT(DISTINCT debit_notes.id) as return_count,
                COALESCE(SUM(debit_note_items.quantity * debit_note_items.rate), 0) as subtotal,
                COALESCE(SUM(debit_note_items.discount_amount), 0) as discount,
                COALESCE(SUM(debit_note_items.tax_amount), 0) as tax_amount
            ')
            ->first();

        $sSubtotal = (float) ($summaryRow->subtotal ?? 0);
        $sDiscount = (float) ($summaryRow->discount ?? 0);
        $sTax = (float) ($summaryRow->tax_amount ?? 0);

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $rows->all(),
            'summary' => [
                'return_count' => (int) ($summaryRow->return_count ?? 0),
                'subtotal' => round($sSubtotal, 2),
                'discount' => round($sDiscount, 2),
                'tax_amount' => round($sTax, 2),
                'total_amount' => round($sSubtotal - $sDiscount + $sTax, 2),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function outstandingPurchase(Request $request): array
    {
        $companyId = TenantService::companyId();
        $today = Carbon::today()->toDateString();

        $balanceExpr = 'COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) + COALESCE(bills.opening_amount, 0) - COALESCE(pt.paid_total, 0)';

        $paginator = DB::table('bills')
            ->join('parties', 'parties.id', '=', 'bills.party_id')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->leftJoinSub($this->paidSubQuery($companyId), 'pt', fn ($j) => $j->on('bills.id', '=', 'pt.payable_id'))
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->when($request->boolean('overdue_only'), fn ($q) => $q->whereRaw('bills.due_date < ?', [$today]))
            ->whereRaw("($balanceExpr) > 0")
            ->selectRaw("
                bills.id,
                bills.bill_no,
                bills.bill_date,
                bills.due_date,
                bills.party_id,
                parties.name as party_name,
                COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) + COALESCE(bills.opening_amount, 0) as net_total,
                COALESCE(pt.paid_total, 0) as paid_total,
                {$balanceExpr} as balance_due
            ")
            ->orderBy('bills.due_date')
            ->orderByDesc('bills.id')
            ->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(function ($row) use ($today) {
            $netTotal = round((float) $row->net_total, 2);
            $paidTotal = round((float) $row->paid_total, 2);
            $balanceDue = round(max((float) $row->balance_due, 0), 2);
            $daysOverdue = $row->due_date && $row->due_date < $today
                ? Carbon::parse($row->due_date)->diffInDays(Carbon::today())
                : 0;

            return [
                'id' => $row->id,
                'bill_no' => $row->bill_no,
                'bill_date' => $row->bill_date,
                'due_date' => $row->due_date,
                'party_name' => $row->party_name,
                'net_total' => $netTotal,
                'paid_total' => $paidTotal,
                'balance_due' => $balanceDue,
                'days_overdue' => (int) $daysOverdue,
                'is_overdue' => $daysOverdue > 0,
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $rows->all(),
            'summary' => [
                'bill_count' => $paginator->total(),
                'balance_due' => round((float) $rows->sum('balance_due'), 2),
                'overdue_count' => $rows->filter(fn ($r) => $r['is_overdue'])->count(),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function purchaseTax(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $rows = DB::table('bill_items')
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->leftJoin('taxes', 'taxes.id', '=', 'bill_items.tax_id')
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereNull('bill_items.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->selectRaw("
                bill_items.tax_id,
                COALESCE(taxes.name, 'No Tax') as tax_name,
                COALESCE(taxes.rate, 0) as tax_rate,
                COUNT(DISTINCT bills.id) as bill_count,
                SUM(bill_items.quantity * bill_items.rate - bill_items.discount_amount) as taxable_amount,
                SUM(bill_items.tax_amount) as tax_amount
            ")
            ->groupBy('bill_items.tax_id', 'taxes.name', 'taxes.rate')
            ->orderByRaw('SUM(bill_items.tax_amount) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            return [
                'tax_id' => $row->tax_id,
                'tax_name' => $row->tax_name,
                'tax_rate' => round((float) $row->tax_rate, 2),
                'bill_count' => (int) $row->bill_count,
                'taxable_amount' => round((float) $row->taxable_amount, 2),
                'tax_amount' => round((float) $row->tax_amount, 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'taxable_amount' => round((float) $mapped->sum('taxable_amount'), 2),
                'tax_amount' => round((float) $mapped->sum('tax_amount'), 2),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function purchaseLedger(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        if (! $request->filled('party_id')) {
            return [
                'period' => $this->buildPeriod($request),
                'party' => null,
                'opening_balance' => 0,
                'rows' => [],
                'closing_balance' => 0,
                'party_options' => $this->supplierOptions(),
            ];
        }

        $partyId = (int) $request->party_id;
        $party = Party::find($partyId, ['id', 'name', 'code', 'pan']);
        $openingBalance = $this->computeOpeningBalance($partyId, $fromDate, $companyId);

        $bills = DB::table('bills')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->where('bills.party_id', $partyId)
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->selectRaw('
                bills.bill_no as reference,
                bills.bill_date as date,
                CASE WHEN bills.is_opening = 1 THEN "Opening Balance" ELSE "Bill" END as type,
                0 as debit,
                ROUND(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) + COALESCE(bills.opening_amount, 0), 2) as credit
            ')
            ->get();

        $payments = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->join('bills as b', 'b.id', '=', 'pa.payable_id')
            ->where('pa.payable_type', (new Bill)->getMorphClass())
            ->where('p.status', StatusEnum::APPROVED->value)
            ->where('p.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'p.branch_id'))
            ->where('b.party_id', $partyId)
            ->whereNull('p.deleted_at')
            ->whereNull('pa.deleted_at')
            ->whereBetween('p.payment_date', [$fromDate, $toDate])
            ->selectRaw('
                p.payment_no as reference,
                p.payment_date as date,
                "Payment" as type,
                pa.amount as debit,
                0 as credit
            ')
            ->get();

        $dnItemsSub = DB::table('debit_note_items')
            ->selectRaw('debit_note_id, SUM(quantity * rate) - SUM(discount_amount) + SUM(tax_amount) as total')
            ->whereNull('deleted_at')
            ->groupBy('debit_note_id');

        $debitNotes = DB::table('debit_notes')
            ->leftJoinSub($dnItemsSub, 'dnt', fn ($j) => $j->on('debit_notes.id', '=', 'dnt.debit_note_id'))
            ->where('debit_notes.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'debit_notes.branch_id'))
            ->where('debit_notes.party_id', $partyId)
            ->where('debit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->whereBetween('debit_notes.debit_note_date', [$fromDate, $toDate])
            ->selectRaw('
                debit_notes.debit_note_no as reference,
                debit_notes.debit_note_date as date,
                "Debit Note" as type,
                COALESCE(dnt.total, 0) as debit,
                0 as credit
            ')
            ->get();

        $transactions = collect()
            ->concat($bills)
            ->concat($payments)
            ->concat($debitNotes)
            ->sortBy([['date', 'asc'], ['reference', 'asc']])
            ->values();

        $balance = $openingBalance;
        $rows = $transactions->map(function ($tx) use (&$balance) {
            $debit = round((float) $tx->debit, 2);
            $credit = round((float) $tx->credit, 2);
            $balance = round($balance + $credit - $debit, 2);

            return [
                'date' => $tx->date,
                'type' => $tx->type,
                'reference' => $tx->reference,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'party' => $party ? [
                'id' => $party->id,
                'name' => $party->name,
                'code' => $party->code,
                'pan' => $party->pan ?? '',
            ] : null,
            'opening_balance' => round($openingBalance, 2),
            'rows' => $rows->all(),
            'closing_balance' => round($balance, 2),
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function grnReport(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $grnItemsSub = DB::table('grn_items')
            ->selectRaw('goods_received_note_id, COUNT(*) as item_count, SUM(received_qty) as total_received_qty, SUM(billed_qty) as total_billed_qty')
            ->groupBy('goods_received_note_id');

        $rows = DB::table('goods_received_notes as g')
            ->join('parties as p', 'p.id', '=', 'g.party_id')
            ->join('warehouses as w', 'w.id', '=', 'g.warehouse_id')
            ->joinSub($grnItemsSub, 'gi', fn ($j) => $j->on('gi.goods_received_note_id', '=', 'g.id'))
            ->where('g.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'g.branch_id'))
            ->where('g.status', StatusEnum::APPROVED->value)
            ->whereNull('g.deleted_at')
            ->whereBetween('g.received_date', [$fromDate, $toDate])
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('g.warehouse_id', $request->warehouse_id))
            ->when($request->filled('billing_status'), fn ($q) => $q->where('g.billing_status', $request->billing_status))
            ->when($request->filled('party_id'), fn ($q) => $q->where('g.party_id', $request->party_id))
            ->selectRaw('
                g.id,
                g.grn_no,
                g.received_date,
                p.name as party_name,
                w.name as warehouse_name,
                g.billing_status,
                g.remarks,
                gi.item_count,
                gi.total_received_qty,
                gi.total_billed_qty
            ')
            ->orderByDesc('g.received_date')
            ->orderByDesc('g.id')
            ->get();

        $mapped = $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'grn_no' => $row->grn_no,
                'received_date' => $row->received_date,
                'party_name' => $row->party_name,
                'warehouse_name' => $row->warehouse_name,
                'billing_status' => $row->billing_status,
                'remarks' => $row->remarks ?? '',
                'item_count' => (int) $row->item_count,
                'total_received_qty' => round((float) $row->total_received_qty, 2),
                'total_billed_qty' => round((float) $row->total_billed_qty, 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'total_grns' => $mapped->count(),
                'total_qty' => round((float) $mapped->sum('total_received_qty'), 2),
                'billed_qty' => round((float) $mapped->sum('total_billed_qty'), 2),
            ],
            'party_options' => $this->supplierOptions(),
            'warehouse_options' => $this->warehouseOptions(),
        ];
    }

    public function pendingPurchase(Request $request): array
    {
        $companyId = TenantService::companyId();
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $receivedSub = DB::table('grn_items as gi')
            ->join('goods_received_notes as grn', 'grn.id', '=', 'gi.goods_received_note_id')
            ->where('grn.status', StatusEnum::APPROVED->value)
            ->whereNull('grn.deleted_at')
            ->whereNotNull('gi.purchase_order_item_id')
            ->selectRaw('gi.purchase_order_item_id, SUM(gi.received_qty) as received_qty')
            ->groupBy('gi.purchase_order_item_id');

        $rows = DB::table('purchase_orders as po')
            ->join('purchase_order_items as poi', 'poi.purchase_order_id', '=', 'po.id')
            ->leftJoin('parties as pa', 'pa.id', '=', 'po.party_id')
            ->leftJoinSub($receivedSub, 'ri', fn ($j) => $j->on('ri.purchase_order_item_id', '=', 'poi.id'))
            ->where('po.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'po.branch_id'))
            ->where('po.status', StatusEnum::APPROVED->value)
            ->whereNull('po.deleted_at')
            ->whereNull('poi.deleted_at')
            ->whereBetween('po.order_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('po.party_id', $request->party_id))
            ->selectRaw('
                po.id,
                po.order_no,
                po.order_date,
                po.party_id,
                COALESCE(pa.name, "-") as party_name,
                SUM(poi.quantity) as ordered_qty,
                COALESCE(SUM(ri.received_qty), 0) as received_qty
            ')
            ->groupBy('po.id', 'po.order_no', 'po.order_date', 'po.party_id', 'pa.name')
            ->havingRaw('ordered_qty > received_qty')
            ->orderByDesc('po.order_date')
            ->get();

        $mapped = $rows->map(function ($row) {
            $orderedQty = round((float) $row->ordered_qty, 2);
            $receivedQty = round((float) $row->received_qty, 2);

            return [
                'id' => $row->id,
                'order_no' => $row->order_no,
                'order_date' => $row->order_date,
                'party_name' => $row->party_name,
                'ordered_qty' => $orderedQty,
                'received_qty' => $receivedQty,
                'pending_qty' => round($orderedQty - $receivedQty, 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'total_orders' => $mapped->count(),
                'total_ordered_qty' => round((float) $mapped->sum('ordered_qty'), 2),
                'total_pending_qty' => round((float) $mapped->sum('pending_qty'), 2),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    public function purchaseDiscount(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        $paginator = DB::table('bills')
            ->join('parties', 'parties.id', '=', 'bills.party_id')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->whereRaw('(COALESCE(it.line_discount, 0) + COALESCE(discounts.amount, 0)) > 0')
            ->selectRaw('
                bills.id,
                bills.bill_no,
                bills.bill_date,
                parties.name as party_name,
                COALESCE(it.subtotal, 0) as subtotal,
                COALESCE(it.line_discount, 0) as line_discount,
                COALESCE(discounts.amount, 0) as order_discount,
                COALESCE(it.tax_amount, 0) as tax_amount
            ')
            ->orderByDesc('bills.bill_date')
            ->orderByDesc('bills.id')
            ->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $lineDiscount = (float) $row->line_discount;
            $orderDiscount = (float) $row->order_discount;
            $totalDiscount = $lineDiscount + $orderDiscount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'id' => $row->id,
                'bill_no' => $row->bill_no,
                'bill_date' => $row->bill_date,
                'party_name' => $row->party_name,
                'subtotal' => round($subtotal, 2),
                'line_discount' => round($lineDiscount, 2),
                'order_discount' => round($orderDiscount, 2),
                'total_discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_amount' => round($subtotal - $totalDiscount + $taxAmount, 2),
            ];
        })->values();

        $summaryRow = DB::table('bills')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->whereRaw('(COALESCE(it.line_discount, 0) + COALESCE(discounts.amount, 0)) > 0')
            ->selectRaw('
                COUNT(bills.id) as bill_count,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount
            ')
            ->first();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $rows->all(),
            'summary' => [
                'bill_count' => (int) ($summaryRow->bill_count ?? 0),
                'line_discount' => round((float) ($summaryRow->line_discount ?? 0), 2),
                'order_discount' => round((float) ($summaryRow->order_discount ?? 0), 2),
                'total_discount' => round(
                    (float) ($summaryRow->line_discount ?? 0) + (float) ($summaryRow->order_discount ?? 0),
                    2
                ),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'party_options' => $this->supplierOptions(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function computeOpeningBalance(int $partyId, string $fromDate, int $companyId): float
    {
        $openingCr = DB::table('bills')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->where('bills.party_id', $partyId)
            ->where('bills.bill_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) + COALESCE(bills.opening_amount, 0)), 0) as total')
            ->value('total');

        $openingDrPayments = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->join('bills as b', 'b.id', '=', 'pa.payable_id')
            ->where('pa.payable_type', (new Bill)->getMorphClass())
            ->where('p.status', StatusEnum::APPROVED->value)
            ->where('p.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'p.branch_id'))
            ->where('b.party_id', $partyId)
            ->whereNull('p.deleted_at')
            ->whereNull('pa.deleted_at')
            ->where('p.payment_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(pa.amount), 0) as total')
            ->value('total');

        $dnSub = DB::table('debit_note_items')
            ->selectRaw('debit_note_id, SUM(quantity * rate) - SUM(discount_amount) + SUM(tax_amount) as total')
            ->whereNull('deleted_at')
            ->groupBy('debit_note_id');

        $openingDrDebitNotes = DB::table('debit_notes')
            ->leftJoinSub($dnSub, 'dnt', fn ($j) => $j->on('debit_notes.id', '=', 'dnt.debit_note_id'))
            ->where('debit_notes.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'debit_notes.branch_id'))
            ->where('debit_notes.party_id', $partyId)
            ->where('debit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('debit_notes.voided_at')
            ->whereNull('debit_notes.deleted_at')
            ->where('debit_notes.debit_note_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(COALESCE(dnt.total, 0)), 0) as total')
            ->value('total');

        return round((float) $openingCr - (float) $openingDrPayments - (float) $openingDrDebitNotes, 2);
    }

    private function groupedBillQuery(Request $request, string $groupExpr, string $alias): Builder
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();

        return DB::table('bills')
            ->leftJoinSub($this->billItemsSubQuery(), 'it', fn ($j) => $j->on('bills.id', '=', 'it.bill_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', (new Bill)->getMorphClass());
            })
            ->where('bills.company_id', $companyId)
            ->tap(fn ($q) => BranchScope::apply($q, 'bills.branch_id'))
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->selectRaw("
                {$groupExpr} as {$alias},
                COUNT(bills.id) as bill_count,
                COALESCE(SUM(it.subtotal), 0) as subtotal,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount,
                COALESCE(SUM(it.tax_amount), 0) as tax_amount
            ")
            ->groupByRaw($groupExpr)
            ->orderByRaw($groupExpr);
    }

    private function mapAggregateRow(object $row, string $dateKey): array
    {
        $subtotal = (float) $row->subtotal;
        $totalDiscount = (float) $row->line_discount + (float) $row->order_discount;
        $taxAmount = (float) $row->tax_amount;

        return [
            $dateKey => $row->{$dateKey},
            'bill_count' => (int) $row->bill_count,
            'subtotal' => round($subtotal, 2),
            'discount' => round($totalDiscount, 2),
            'tax_amount' => round($taxAmount, 2),
            'net_purchases' => round($subtotal - $totalDiscount + $taxAmount, 2),
        ];
    }

    private function summarizeRows(Collection $rows): array
    {
        return [
            'bill_count' => (int) $rows->sum('bill_count'),
            'subtotal' => round((float) $rows->sum('subtotal'), 2),
            'discount' => round((float) $rows->sum('discount'), 2),
            'tax_amount' => round((float) $rows->sum('tax_amount'), 2),
            'net_purchases' => round((float) $rows->sum('net_purchases'), 2),
        ];
    }

    private function billItemsSubQuery(): Builder
    {
        return DB::table('bill_items')
            ->selectRaw('bill_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as line_discount, SUM(tax_amount) as tax_amount')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');
    }

    private function paidSubQuery(int $companyId): Builder
    {
        return DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->selectRaw('pa.payable_id, SUM(pa.amount) as paid_total')
            ->where('pa.payable_type', (new Bill)->getMorphClass())
            ->where('p.status', StatusEnum::APPROVED->value)
            ->where('p.company_id', $companyId)
            ->whereNull('pa.deleted_at')
            ->whereNull('p.deleted_at')
            ->groupBy('pa.payable_id');
    }

    private function monthGroupExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-01', bills.bill_date)"
            : "DATE_FORMAT(bills.bill_date, '%Y-%m-01')";
    }

    private function yearGroupExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y', bills.bill_date)"
            : 'YEAR(bills.bill_date)';
    }

    private function resolveFromDate(Request $request): Carbon
    {
        if ($request->filled('from_date')) {
            return Carbon::parse($request->from_date)->startOfDay();
        }

        $fiscalYear = auth('admin')->user()?->company?->fiscalYear;

        return $fiscalYear?->start_date?->copy()->startOfDay() ?? now()->startOfMonth()->startOfDay();
    }

    private function resolveToDate(Request $request): Carbon
    {
        if ($request->filled('to_date')) {
            return Carbon::parse($request->to_date)->endOfDay();
        }

        $fiscalYear = auth('admin')->user()?->company?->fiscalYear;

        return $fiscalYear?->end_date?->copy()->endOfDay() ?? now()->endOfDay();
    }

    private function buildPeriod(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request);
        $toDate = $this->resolveToDate($request);

        return [
            'from_date' => $fromDate->toDateString(),
            'to_date' => $toDate->toDateString(),
            'label' => $fromDate->format('d M Y').' - '.$toDate->format('d M Y'),
        ];
    }

    private function supplierOptions(): array
    {
        return Party::query()
            ->where('type', PartyTypeEnum::SUPPLIER)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Party $p) => ['id' => (string) $p->id, 'name' => $p->name])
            ->all();
    }

    private function categoryOptions(): array
    {
        return DB::table('product_categories')
            ->where('company_id', TenantService::companyId())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name])
            ->all();
    }

    private function warehouseOptions(): array
    {
        return DB::table('warehouses')
            ->where('company_id', TenantService::companyId())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($w) => ['id' => (string) $w->id, 'name' => $w->name])
            ->all();
    }
}
