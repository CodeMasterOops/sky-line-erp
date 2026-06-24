<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Models\Party;
use App\Models\Stock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\FiscalYear;
use App\Enums\DateModeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Nepal\DateDisplayService;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;

        [$from, $to, $fiscalYear] = $this->getDateRange($request);

        [$salesTotal, $salesReturnTotal, $purchaseTotal, $purchaseReturnTotal] = $this->financialTotals($companyId, $from, $to);

        return response()->json([
            'total_sales' => round((float) $salesTotal, 2),
            'total_sales_return' => round((float) $salesReturnTotal, 2),
            'total_purchase' => round((float) $purchaseTotal, 2),
            'total_purchase_return' => round((float) $purchaseReturnTotal, 2),
            'customers_count' => Party::where('type', 'customer')->count(),
            'suppliers_count' => Party::where('type', 'supplier')->count(),
            'products_count' => Product::count(),
            'orders_today' => Invoice::whereDate('invoice_date', today())->count(),
            'top_selling_products' => $this->topSellingProducts($companyId, $from, $to),
            'low_stock_products' => $this->lowStockProducts($companyId),
            'recent_transactions' => [
                'invoices' => $this->recentInvoices($companyId, $from, $to),
                'bills' => $this->recentBills($companyId, $from, $to),
                'quotations' => $this->recentQuotations($companyId, $from, $to),
                'expenses' => $this->recentExpenses($companyId, $from, $to),
            ],
            'top_customers' => $this->topCustomers($companyId, $from, $to),
            'chart_data' => $this->chartData($companyId, $from, $to),
            'fiscal_year' => [
                'start_date' => $fiscalYear ? $fiscalYear->start_date->toDateString() : Carbon::now()->startOfYear()->toDateString(),
                'end_date' => $fiscalYear ? $fiscalYear->end_date->toDateString() : Carbon::now()->endOfYear()->toDateString(),
            ],
            'filter' => [
                'date_from' => $from,
                'date_to' => $to,
            ],
        ]);
    }

    /** Resolve the active date range from request params or current fiscal year. */
    private function getDateRange(Request $request): array
    {
        $fiscalYear = FiscalYear::where('is_current', true)->first();

        $defaultFrom = $fiscalYear
            ? $fiscalYear->start_date->toDateString()
            : Carbon::now()->startOfYear()->toDateString();

        $defaultTo = today()->toDateString();

        $from = $request->filled('date_from') ? $request->input('date_from') : $defaultFrom;
        $to = $request->filled('date_to') ? $request->input('date_to') : $defaultTo;

        return [$from, $to, $fiscalYear];
    }

    /** Financial totals using raw aggregation – no model hydration. */
    private function financialTotals(int $companyId, string $from, string $to): array
    {
        $itemTotal = fn (string $itemTable, string $fk, string $parentTable, string $dateCol) => DB::table($itemTable)
            ->join($parentTable, "{$parentTable}.id", '=', "{$itemTable}.{$fk}")
            ->where("{$parentTable}.company_id", $companyId)
            ->whereNull("{$parentTable}.deleted_at")
            ->whereNull("{$itemTable}.deleted_at")
            ->whereBetween("{$parentTable}.{$dateCol}", [$from, $to])
            ->selectRaw("COALESCE(SUM({$itemTable}.quantity * {$itemTable}.rate - {$itemTable}.discount_amount), 0) as total")
            ->value('total') ?? 0;

        return [
            $itemTotal('invoice_items', 'invoice_id', 'invoices', 'invoice_date'),
            $itemTotal('credit_note_items', 'credit_note_id', 'credit_notes', 'credit_note_date'),
            $itemTotal('bill_items', 'bill_id', 'bills', 'bill_date'),
            $itemTotal('debit_note_items', 'debit_note_id', 'debit_notes', 'debit_note_date'),
        ];
    }

    /** Top 5 products by units sold – single aggregation query. */
    private function topSellingProducts(int $companyId, string $from, string $to): array
    {
        return DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->join('product_variants as pv', 'pv.id', '=', 'ii.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at')
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->groupBy('p.id', 'p.name', 'p.code', 'b.name', 'pc.name')
            ->selectRaw('
                p.id,
                p.name,
                p.code,
                b.name  AS brand_name,
                pc.name AS category_name,
                SUM(ii.quantity) AS sold_qty,
                SUM(ii.quantity * ii.rate - ii.discount_amount) AS sold_amount
            ')
            ->orderByDesc('sold_qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Low stock products (quantity ≤ min_stock_level) – single JOIN query. Not date-filtered. */
    private function lowStockProducts(int $companyId): array
    {
        return DB::table('stocks as s')
            ->join('product_variants as pv', 'pv.id', '=', 's.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 's.warehouse_id')
            ->where('s.company_id', $companyId)
            ->whereNull('s.deleted_at')
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereNull('w.deleted_at')
            ->whereRaw('p.min_stock_level > 0')
            ->whereRaw('s.quantity <= p.min_stock_level')
            ->selectRaw('
                p.name  AS product_name,
                p.code  AS product_code,
                pv.sku,
                w.name  AS warehouse_name,
                s.quantity,
                p.min_stock_level,
                p.reorder_quantity
            ')
            ->orderBy('s.quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Recent invoices with per-document total in one query (JOIN + GROUP BY). */
    private function recentInvoices(int $companyId, string $from, string $to): array
    {
        return DB::table('invoices as inv')
            ->leftJoin('invoice_items as ii', fn ($j) => $j->on('ii.invoice_id', '=', 'inv.id')->whereNull('ii.deleted_at'))
            ->leftJoin('parties as p', 'p.id', '=', 'inv.party_id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->groupBy('inv.id', 'inv.invoice_no', 'inv.invoice_date', 'inv.status', 'p.name')
            ->selectRaw('
                inv.id,
                inv.invoice_no,
                inv.invoice_date,
                inv.status,
                p.name AS party_name,
                COALESCE(SUM(ii.quantity * ii.rate - ii.discount_amount), 0) AS total
            ')
            ->orderByDesc('inv.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Recent bills with per-document total. */
    private function recentBills(int $companyId, string $from, string $to): array
    {
        return DB::table('bills as b')
            ->leftJoin('bill_items as bi', fn ($j) => $j->on('bi.bill_id', '=', 'b.id')->whereNull('bi.deleted_at'))
            ->leftJoin('parties as p', 'p.id', '=', 'b.party_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereBetween('b.bill_date', [$from, $to])
            ->groupBy('b.id', 'b.bill_no', 'b.bill_date', 'b.status', 'p.name')
            ->selectRaw('
                b.id,
                b.bill_no,
                b.bill_date,
                b.status,
                p.name AS party_name,
                COALESCE(SUM(bi.quantity * bi.rate - bi.discount_amount), 0) AS total
            ')
            ->orderByDesc('b.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Recent quotations with per-document total. */
    private function recentQuotations(int $companyId, string $from, string $to): array
    {
        return DB::table('quotations as q')
            ->leftJoin('quotation_items as qi', fn ($j) => $j->on('qi.quotation_id', '=', 'q.id')->whereNull('qi.deleted_at'))
            ->leftJoin('parties as p', 'p.id', '=', 'q.party_id')
            ->where('q.company_id', $companyId)
            ->whereNull('q.deleted_at')
            ->whereBetween('q.quotation_date', [$from, $to])
            ->groupBy('q.id', 'q.quotation_no', 'q.quotation_date', 'q.status', 'p.name')
            ->selectRaw('
                q.id,
                q.quotation_no,
                q.quotation_date,
                q.status,
                p.name AS party_name,
                COALESCE(SUM(qi.quantity * qi.rate - qi.discount_amount), 0) AS total
            ')
            ->orderByDesc('q.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Recent expenses with per-document total (expense_items use `amount` not quantity×rate). */
    private function recentExpenses(int $companyId, string $from, string $to): array
    {
        return DB::table('expenses as e')
            ->leftJoin('expense_items as ei', fn ($j) => $j->on('ei.expense_id', '=', 'e.id')->whereNull('ei.deleted_at'))
            ->leftJoin('parties as p', 'p.id', '=', 'e.party_id')
            ->where('e.company_id', $companyId)
            ->whereNull('e.deleted_at')
            ->whereBetween('e.date', [$from, $to])
            ->groupBy('e.id', 'e.expense_no', 'e.date', 'e.status', 'p.name')
            ->selectRaw('
                e.id,
                e.expense_no,
                e.date,
                e.status,
                p.name AS party_name,
                COALESCE(SUM(ei.amount), 0) AS total
            ')
            ->orderByDesc('e.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /** Top 5 customers by total invoice amount – single GROUP BY query. */
    private function topCustomers(int $companyId, string $from, string $to): array
    {
        return DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->join('parties as p', 'p.id', '=', 'inv.party_id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at')
            ->whereNull('p.deleted_at')
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->groupBy('p.id', 'p.name', 'p.address')
            ->selectRaw('
                p.id,
                p.name,
                p.address,
                COUNT(DISTINCT inv.id) AS order_count,
                SUM(ii.quantity * ii.rate - ii.discount_amount) AS total_amount
            ')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Monthly breakdown between $from and $to.
     * Generates one label per calendar month in range.
     */
    private function chartData(int $companyId, string $from, string $to): array
    {
        $salesByMonth = DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->where('inv.company_id', $companyId)
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at')
            ->selectRaw("DATE_FORMAT(inv.invoice_date, '%Y-%m') as month, SUM(ii.quantity * ii.rate - ii.discount_amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $purchasesByMonth = DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->where('b.company_id', $companyId)
            ->whereBetween('b.bill_date', [$from, $to])
            ->whereNull('b.deleted_at')
            ->whereNull('bi.deleted_at')
            ->selectRaw("DATE_FORMAT(b.bill_date, '%Y-%m') as month, SUM(bi.quantity * bi.rate - bi.discount_amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $expensesByMonth = DB::table('expense_items as ei')
            ->join('expenses as e', 'e.id', '=', 'ei.expense_id')
            ->where('e.company_id', $companyId)
            ->whereBetween('e.date', [$from, $to])
            ->whereNull('e.deleted_at')
            ->whereNull('ei.deleted_at')
            ->selectRaw("DATE_FORMAT(e.date, '%Y-%m') as month, SUM(ei.amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();

        $months = collect();
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $months->push($cursor->format('Y-m'));
            $cursor->addMonth();
        }

        $display = app(DateDisplayService::class);

        return [
            'labels' => $months->map(fn ($m) => $display->monthLabel($m.'-01', DateModeEnum::Ad))->values()->all(),
            'labels_bs' => $months->map(fn ($m) => $display->monthLabel($m.'-01', DateModeEnum::Bs))->values()->all(),
            'sales' => $months->map(fn ($m) => round((float) ($salesByMonth[$m] ?? 0), 2))->values()->all(),
            'purchases' => $months->map(fn ($m) => round((float) ($purchasesByMonth[$m] ?? 0), 2))->values()->all(),
            'expenses' => $months->map(fn ($m) => round((float) ($expensesByMonth[$m] ?? 0), 2))->values()->all(),
        ];
    }
}
