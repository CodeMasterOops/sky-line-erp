<?php

namespace App\Services\Sales;

use Carbon\Carbon;
use App\Models\Party;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Services\TenantService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

class SalesReportService
{
    public function salesSummary(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $row = DB::table('invoices')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM(it.subtotal), 0) as subtotal,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount,
                COALESCE(SUM(it.tax_amount), 0) as tax_amount
            ')
            ->first();

        $subtotal = (float) $row->subtotal;
        $totalDiscount = (float) $row->line_discount + (float) $row->order_discount;
        $taxAmount = (float) $row->tax_amount;
        $netSales = $subtotal - $totalDiscount + $taxAmount;

        $returnRow = DB::table('credit_notes')
            ->leftJoin('credit_note_items', function ($j) {
                $j->on('credit_notes.id', '=', 'credit_note_items.credit_note_id')
                    ->whereNull('credit_note_items.deleted_at');
            })
            ->where('credit_notes.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('credit_notes.branch_id', $branchId))
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereBetween('credit_notes.credit_note_date', [$fromDate, $toDate])
            ->selectRaw('
                COUNT(DISTINCT credit_notes.id) as return_count,
                COALESCE(SUM(credit_note_items.quantity * credit_note_items.rate) - SUM(credit_note_items.discount_amount) + SUM(credit_note_items.tax_amount), 0) as total_returns
            ')
            ->first();

        $paidRow = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->join('invoices as inv', 'inv.id', '=', 'receipt_allocations.invoice_id')
            ->where('inv.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('inv.branch_id', $branchId))
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereBetween('inv.invoice_date', [$fromDate, $toDate])
            ->selectRaw('COALESCE(SUM(receipt_allocations.amount), 0) as total_paid')
            ->first();

        $totalReturns = round((float) ($returnRow->total_returns ?? 0), 2);
        $totalPaid = round((float) ($paidRow->total_paid ?? 0), 2);

        return [
            'period' => $this->buildPeriod($request),
            'summary' => [
                'invoice_count' => (int) $row->invoice_count,
                'return_count' => (int) ($returnRow->return_count ?? 0),
                'subtotal' => round($subtotal, 2),
                'total_discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_sales' => round($netSales, 2),
                'total_returns' => $totalReturns,
                'net_revenue' => round($netSales - $totalReturns, 2),
                'total_paid' => $totalPaid,
                'total_outstanding' => round(max($netSales - $totalPaid, 0), 2),
            ],
            'party_options' => $this->partyOptions(),
        ];
    }

    public function dailySales(Request $request): array
    {
        $rows = $this->groupedSalesQuery($request, 'DATE(invoices.invoice_date)', 'sale_date')->get();
        $mapped = $rows->map(fn ($row) => $this->mapAggregateRow($row, 'sale_date'))->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
            'party_options' => $this->partyOptions(),
        ];
    }

    public function monthlySales(Request $request): array
    {
        $rows = $this->groupedSalesQuery($request, $this->monthGroupExpr(), 'sale_month')->get();

        $mapped = $rows->map(function ($row) {
            $base = $this->mapAggregateRow($row, 'sale_month');
            $date = Carbon::parse($row->sale_month);
            $base['month_label'] = $date->format('M Y');
            $base['year'] = (int) $date->format('Y');
            $base['month'] = (int) $date->format('m');

            return $base;
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
            'party_options' => $this->partyOptions(),
        ];
    }

    public function yearlySales(Request $request): array
    {
        $rows = $this->groupedSalesQuery($request, $this->yearGroupExpr(), 'sale_year')->get();
        $mapped = $rows->map(fn ($row) => $this->mapAggregateRow($row, 'sale_year'))->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => $this->summarizeRows($mapped),
        ];
    }

    public function customerWiseSales(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as paid_total')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->groupBy('receipt_allocations.invoice_id');

        $rows = DB::table('invoices')
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->leftJoinSub($paidSub, 'pt', fn ($j) => $j->on('invoices.id', '=', 'pt.invoice_id'))
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->selectRaw('
                invoices.party_id,
                parties.name as party_name,
                parties.code as party_code,
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM(it.subtotal), 0) as subtotal,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount,
                COALESCE(SUM(it.tax_amount), 0) as tax_amount,
                COALESCE(SUM(pt.paid_total), 0) as paid_total
            ')
            ->groupBy('invoices.party_id', 'parties.name', 'parties.code')
            ->orderByRaw('SUM(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0)) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $totalDiscount = (float) $row->line_discount + (float) $row->order_discount;
            $taxAmount = (float) $row->tax_amount;
            $netSales = round($subtotal - $totalDiscount + $taxAmount, 2);
            $paid = round((float) $row->paid_total, 2);

            return [
                'party_id' => $row->party_id,
                'party_name' => $row->party_name,
                'party_code' => $row->party_code ?? '',
                'invoice_count' => (int) $row->invoice_count,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_sales' => $netSales,
                'paid' => $paid,
                'outstanding' => round(max($netSales - $paid, 0), 2),
            ];
        })->values();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'invoice_count' => (int) $mapped->sum('invoice_count'),
                'subtotal' => round((float) $mapped->sum('subtotal'), 2),
                'discount' => round((float) $mapped->sum('discount'), 2),
                'tax_amount' => round((float) $mapped->sum('tax_amount'), 2),
                'net_sales' => round((float) $mapped->sum('net_sales'), 2),
                'paid' => round((float) $mapped->sum('paid'), 2),
                'outstanding' => round((float) $mapped->sum('outstanding'), 2),
            ],
        ];
    }

    public function categoryWiseSales(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('product_variants', 'product_variants.id', '=', 'invoice_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_items.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->selectRaw("
                COALESCE(products.product_category_id, 0) as category_id,
                COALESCE(product_categories.name, 'Uncategorized') as category_name,
                COUNT(DISTINCT invoices.id) as invoice_count,
                COALESCE(SUM(invoice_items.quantity), 0) as total_qty,
                COALESCE(SUM(invoice_items.quantity * invoice_items.rate), 0) as subtotal,
                COALESCE(SUM(invoice_items.discount_amount), 0) as line_discount,
                COALESCE(SUM(invoice_items.tax_amount), 0) as tax_amount
            ")
            ->groupBy('products.product_category_id', 'product_categories.name')
            ->orderByRaw('SUM(invoice_items.quantity * invoice_items.rate) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $discount = (float) $row->line_discount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'invoice_count' => (int) $row->invoice_count,
                'total_qty' => round((float) $row->total_qty, 2),
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_sales' => round($subtotal - $discount + $taxAmount, 2),
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
                'net_sales' => round((float) $mapped->sum('net_sales'), 2),
            ],
            'party_options' => $this->partyOptions(),
            'category_options' => $this->categoryOptions(),
        ];
    }

    public function salesReturn(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $cnItemsSub = DB::table('credit_note_items')
            ->selectRaw('credit_note_id, COUNT(*) as item_count, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount, SUM(tax_amount) as tax_amount')
            ->whereNull('deleted_at')
            ->groupBy('credit_note_id');

        $paginator = DB::table('credit_notes')
            ->join('parties', 'parties.id', '=', 'credit_notes.party_id')
            ->leftJoin('invoices as linked_inv', 'linked_inv.id', '=', 'credit_notes.invoice_id')
            ->leftJoinSub($cnItemsSub, 'it', fn ($j) => $j->on('credit_notes.id', '=', 'it.credit_note_id'))
            ->where('credit_notes.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('credit_notes.branch_id', $branchId))
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereBetween('credit_notes.credit_note_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('credit_notes.party_id', $request->party_id))
            ->select([
                'credit_notes.id',
                'credit_notes.credit_note_no',
                'credit_notes.credit_note_date',
                'credit_notes.party_id',
                'parties.name as party_name',
                'linked_inv.invoice_no',
                DB::raw('COALESCE(it.item_count, 0) as item_count'),
                DB::raw('COALESCE(it.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(it.discount, 0) as discount'),
                DB::raw('COALESCE(it.tax_amount, 0) as tax_amount'),
            ])
            ->orderByDesc('credit_notes.credit_note_date')
            ->orderByDesc('credit_notes.id')
            ->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $discount = (float) $row->discount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'id' => $row->id,
                'credit_note_no' => $row->credit_note_no,
                'credit_note_date' => $row->credit_note_date,
                'party_name' => $row->party_name,
                'linked_invoice_no' => $row->invoice_no ?? '-',
                'item_count' => (int) $row->item_count,
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($subtotal - $discount + $taxAmount, 2),
            ];
        })->values();

        $summaryRow = DB::table('credit_notes')
            ->leftJoin('credit_note_items', function ($j) {
                $j->on('credit_notes.id', '=', 'credit_note_items.credit_note_id')
                    ->whereNull('credit_note_items.deleted_at');
            })
            ->where('credit_notes.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('credit_notes.branch_id', $branchId))
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereBetween('credit_notes.credit_note_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('credit_notes.party_id', $request->party_id))
            ->selectRaw('
                COUNT(DISTINCT credit_notes.id) as return_count,
                COALESCE(SUM(credit_note_items.quantity * credit_note_items.rate), 0) as subtotal,
                COALESCE(SUM(credit_note_items.discount_amount), 0) as discount,
                COALESCE(SUM(credit_note_items.tax_amount), 0) as tax_amount
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
            'party_options' => $this->partyOptions(),
        ];
    }

    public function outstandingSales(Request $request): array
    {
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();
        $today = Carbon::today()->toDateString();

        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as paid_total')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->groupBy('receipt_allocations.invoice_id');

        $balanceExpr = 'COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) - COALESCE(pt.paid_total, 0)';

        $paginator = DB::table('invoices')
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->leftJoinSub($paidSub, 'pt', fn ($j) => $j->on('invoices.id', '=', 'pt.invoice_id'))
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->when($request->boolean('overdue_only'), fn ($q) => $q->whereRaw('invoices.due_date < ?', [$today]))
            ->whereRaw("($balanceExpr) > 0")
            ->selectRaw("
                invoices.id,
                invoices.invoice_no,
                invoices.invoice_date,
                invoices.due_date,
                invoices.party_id,
                parties.name as party_name,
                COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0) as net_total,
                COALESCE(pt.paid_total, 0) as paid_total,
                {$balanceExpr} as balance_due
            ")
            ->orderBy('invoices.due_date')
            ->orderByDesc('invoices.id')
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
                'invoice_no' => $row->invoice_no,
                'invoice_date' => $row->invoice_date,
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
                'invoice_count' => $paginator->total(),
                'balance_due' => round((float) $rows->sum('balance_due'), 2),
                'overdue_count' => $rows->filter(fn ($r) => $r['is_overdue'])->count(),
            ],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'party_options' => $this->partyOptions(),
        ];
    }

    public function salesTax(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('taxes', 'taxes.id', '=', 'invoice_items.tax_id')
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_items.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->selectRaw("
                invoice_items.tax_id,
                COALESCE(taxes.name, 'No Tax') as tax_name,
                COALESCE(taxes.rate, 0) as tax_rate,
                COUNT(DISTINCT invoices.id) as invoice_count,
                SUM(invoice_items.quantity * invoice_items.rate - invoice_items.discount_amount) as taxable_amount,
                SUM(invoice_items.tax_amount) as tax_amount
            ")
            ->groupBy('invoice_items.tax_id', 'taxes.name', 'taxes.rate')
            ->orderByRaw('SUM(invoice_items.tax_amount) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            return [
                'tax_id' => $row->tax_id,
                'tax_name' => $row->tax_name,
                'tax_rate' => round((float) $row->tax_rate, 2),
                'invoice_count' => (int) $row->invoice_count,
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
            'party_options' => $this->partyOptions(),
        ];
    }

    public function salesProfit(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $rows = DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('product_variants', 'product_variants.id', '=', 'invoice_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereNull('invoice_items.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->when($request->filled('category_id'), fn ($q) => $q->where('products.product_category_id', $request->category_id))
            ->selectRaw("
                invoice_items.product_variant_id,
                products.name as product_name,
                products.code as product_code,
                product_variants.sku,
                COALESCE(product_categories.name, 'Uncategorized') as category_name,
                SUM(invoice_items.quantity) as total_qty,
                SUM(invoice_items.quantity * invoice_items.rate) as gross_revenue,
                SUM(invoice_items.discount_amount) as total_discount,
                SUM(invoice_items.tax_amount) as tax_amount,
                SUM(invoice_items.quantity * invoice_items.rate) - SUM(invoice_items.discount_amount) as net_revenue,
                SUM(invoice_items.quantity * COALESCE(product_variants.purchase_price, 0)) as total_cost
            ")
            ->groupBy(
                'invoice_items.product_variant_id',
                'products.name',
                'products.code',
                'product_variants.sku',
                'product_categories.name'
            )
            ->orderByRaw('(SUM(invoice_items.quantity * invoice_items.rate) - SUM(invoice_items.discount_amount) - SUM(invoice_items.quantity * COALESCE(product_variants.purchase_price, 0))) DESC')
            ->get();

        $mapped = $rows->map(function ($row) {
            $netRevenue = round((float) $row->net_revenue, 2);
            $totalCost = round((float) $row->total_cost, 2);
            $grossProfit = round($netRevenue - $totalCost, 2);
            $marginPct = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0;

            return [
                'product_variant_id' => $row->product_variant_id,
                'product_name' => $row->product_name,
                'product_code' => $row->product_code ?? '',
                'sku' => $row->sku ?? '',
                'category_name' => $row->category_name,
                'total_qty' => round((float) $row->total_qty, 2),
                'gross_revenue' => round((float) $row->gross_revenue, 2),
                'total_discount' => round((float) $row->total_discount, 2),
                'net_revenue' => $netRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'margin_pct' => $marginPct,
            ];
        })->values();

        $totalNetRevenue = round((float) $mapped->sum('net_revenue'), 2);
        $totalCost = round((float) $mapped->sum('total_cost'), 2);
        $totalGrossProfit = round($totalNetRevenue - $totalCost, 2);

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $mapped->all(),
            'summary' => [
                'total_qty' => round((float) $mapped->sum('total_qty'), 2),
                'gross_revenue' => round((float) $mapped->sum('gross_revenue'), 2),
                'total_discount' => round((float) $mapped->sum('total_discount'), 2),
                'net_revenue' => $totalNetRevenue,
                'total_cost' => $totalCost,
                'gross_profit' => $totalGrossProfit,
                'margin_pct' => $totalNetRevenue > 0
                    ? round(($totalGrossProfit / $totalNetRevenue) * 100, 2)
                    : 0.0,
            ],
            'party_options' => $this->partyOptions(),
            'category_options' => $this->categoryOptions(),
        ];
    }

    public function discountReport(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        $paginator = DB::table('invoices')
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->whereRaw('(COALESCE(it.line_discount, 0) + COALESCE(discounts.amount, 0)) > 0')
            ->selectRaw('
                invoices.id,
                invoices.invoice_no,
                invoices.invoice_date,
                parties.name as party_name,
                COALESCE(it.subtotal, 0) as subtotal,
                COALESCE(it.line_discount, 0) as line_discount,
                COALESCE(discounts.amount, 0) as order_discount,
                COALESCE(it.tax_amount, 0) as tax_amount
            ')
            ->orderByDesc('invoices.invoice_date')
            ->orderByDesc('invoices.id')
            ->paginate($request->input('limit', 50));

        $rows = collect($paginator->items())->map(function ($row) {
            $subtotal = (float) $row->subtotal;
            $lineDiscount = (float) $row->line_discount;
            $orderDiscount = (float) $row->order_discount;
            $totalDiscount = $lineDiscount + $orderDiscount;
            $taxAmount = (float) $row->tax_amount;

            return [
                'id' => $row->id,
                'invoice_no' => $row->invoice_no,
                'invoice_date' => $row->invoice_date,
                'party_name' => $row->party_name,
                'subtotal' => round($subtotal, 2),
                'line_discount' => round($lineDiscount, 2),
                'order_discount' => round($orderDiscount, 2),
                'total_discount' => round($totalDiscount, 2),
                'tax_amount' => round($taxAmount, 2),
                'net_amount' => round($subtotal - $totalDiscount + $taxAmount, 2),
            ];
        })->values();

        $summaryRow = DB::table('invoices')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->whereRaw('(COALESCE(it.line_discount, 0) + COALESCE(discounts.amount, 0)) > 0')
            ->selectRaw('
                COUNT(invoices.id) as invoice_count,
                COALESCE(SUM(it.line_discount), 0) as line_discount,
                COALESCE(SUM(discounts.amount), 0) as order_discount
            ')
            ->first();

        return [
            'period' => $this->buildPeriod($request),
            'rows' => $rows->all(),
            'summary' => [
                'invoice_count' => (int) ($summaryRow->invoice_count ?? 0),
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
            'party_options' => $this->partyOptions(),
        ];
    }

    public function salesLedger(Request $request): array
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        if (! $request->filled('party_id')) {
            return [
                'period' => $this->buildPeriod($request),
                'party' => null,
                'opening_balance' => 0,
                'rows' => [],
                'closing_balance' => 0,
                'party_options' => $this->partyOptions(),
            ];
        }

        $partyId = (int) $request->party_id;
        $party = Party::find($partyId, ['id', 'name', 'code', 'pan']);

        $openingBalance = $this->computeOpeningBalance($partyId, $fromDate, $companyId, $branchId);

        $invoices = DB::table('invoices')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.party_id', $partyId)
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->selectRaw('
                invoices.invoice_no as reference,
                invoices.invoice_date as date,
                "Invoice" as type,
                ROUND(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0), 2) as debit,
                0 as credit
            ')
            ->get();

        $receipts = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('receipts.branch_id', $branchId))
            ->where('receipts.party_id', $partyId)
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereBetween('receipts.receipt_date', [$fromDate, $toDate])
            ->selectRaw('
                receipts.receipt_no as reference,
                receipts.receipt_date as date,
                "Receipt" as type,
                0 as debit,
                receipt_allocations.amount as credit
            ')
            ->get();

        $creditNotesSub = DB::table('credit_note_items')
            ->selectRaw('credit_note_id, SUM(quantity * rate) - SUM(discount_amount) + SUM(tax_amount) as total')
            ->whereNull('deleted_at')
            ->groupBy('credit_note_id');

        $creditNotes = DB::table('credit_notes')
            ->leftJoinSub($creditNotesSub, 'cnt', fn ($j) => $j->on('credit_notes.id', '=', 'cnt.credit_note_id'))
            ->where('credit_notes.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('credit_notes.branch_id', $branchId))
            ->where('credit_notes.party_id', $partyId)
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->whereBetween('credit_notes.credit_note_date', [$fromDate, $toDate])
            ->selectRaw('
                credit_notes.credit_note_no as reference,
                credit_notes.credit_note_date as date,
                "Credit Note" as type,
                0 as debit,
                COALESCE(cnt.total, 0) as credit
            ')
            ->get();

        $transactions = collect()
            ->concat($invoices)
            ->concat($receipts)
            ->concat($creditNotes)
            ->sortBy([['date', 'asc'], ['reference', 'asc']])
            ->values();

        $balance = $openingBalance;
        $rows = $transactions->map(function ($tx) use (&$balance) {
            $debit = round((float) $tx->debit, 2);
            $credit = round((float) $tx->credit, 2);
            $balance = round($balance + $debit - $credit, 2);

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
            'party_options' => $this->partyOptions(),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function computeOpeningBalance(int $partyId, string $fromDate, int $companyId, ?int $branchId): float
    {
        $openingDr = DB::table('invoices')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.party_id', $partyId)
            ->where('invoices.invoice_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(COALESCE(it.subtotal, 0) - COALESCE(it.line_discount, 0) - COALESCE(discounts.amount, 0) + COALESCE(it.tax_amount, 0)), 0) as total')
            ->value('total');

        $openingCr = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->where('receipts.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('receipts.branch_id', $branchId))
            ->where('receipts.party_id', $partyId)
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereNull('receipts.deleted_at')
            ->whereNull('receipt_allocations.deleted_at')
            ->where('receipts.receipt_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(receipt_allocations.amount), 0) as total')
            ->value('total');

        $cnSub = DB::table('credit_note_items')
            ->selectRaw('credit_note_id, SUM(quantity * rate) - SUM(discount_amount) + SUM(tax_amount) as total')
            ->whereNull('deleted_at')
            ->groupBy('credit_note_id');

        $openingCnCr = DB::table('credit_notes')
            ->leftJoinSub($cnSub, 'cnt', fn ($j) => $j->on('credit_notes.id', '=', 'cnt.credit_note_id'))
            ->where('credit_notes.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('credit_notes.branch_id', $branchId))
            ->where('credit_notes.party_id', $partyId)
            ->where('credit_notes.status', StatusEnum::APPROVED->value)
            ->whereNull('credit_notes.voided_at')
            ->whereNull('credit_notes.deleted_at')
            ->where('credit_notes.credit_note_date', '<', $fromDate)
            ->selectRaw('COALESCE(SUM(COALESCE(cnt.total, 0)), 0) as total')
            ->value('total');

        return round((float) $openingDr - (float) $openingCr - (float) $openingCnCr, 2);
    }

    private function groupedSalesQuery(Request $request, string $groupExpr, string $alias): Builder
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $companyId = TenantService::companyId();
        $branchId = TenantService::branchId();

        return DB::table('invoices')
            ->leftJoinSub($this->itemsSubQuery(), 'it', fn ($j) => $j->on('invoices.id', '=', 'it.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.company_id', $companyId)
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId))
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->whereBetween('invoices.invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('invoices.party_id', $request->party_id))
            ->selectRaw("
                {$groupExpr} as {$alias},
                COUNT(invoices.id) as invoice_count,
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
            'invoice_count' => (int) $row->invoice_count,
            'subtotal' => round($subtotal, 2),
            'discount' => round($totalDiscount, 2),
            'tax_amount' => round($taxAmount, 2),
            'net_sales' => round($subtotal - $totalDiscount + $taxAmount, 2),
        ];
    }

    private function summarizeRows(Collection $rows): array
    {
        return [
            'invoice_count' => (int) $rows->sum('invoice_count'),
            'subtotal' => round((float) $rows->sum('subtotal'), 2),
            'discount' => round((float) $rows->sum('discount'), 2),
            'tax_amount' => round((float) $rows->sum('tax_amount'), 2),
            'net_sales' => round((float) $rows->sum('net_sales'), 2),
        ];
    }

    private function itemsSubQuery(): Builder
    {
        return DB::table('invoice_items')
            ->selectRaw('invoice_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as line_discount, SUM(tax_amount) as tax_amount')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');
    }

    private function monthGroupExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-01', invoices.invoice_date)"
            : "DATE_FORMAT(invoices.invoice_date, '%Y-%m-01')";
    }

    private function yearGroupExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y', invoices.invoice_date)"
            : 'YEAR(invoices.invoice_date)';
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

    private function partyOptions(): array
    {
        return Party::query()
            ->where('type', PartyTypeEnum::CUSTOMER)
            ->orderBy('name')
            ->limit(500)
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
}
