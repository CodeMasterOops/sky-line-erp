<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;

        $from    = $request->input('from');
        $to      = $request->input('to');
        $partyId = $request->input('party_id');
        $variantId = $request->input('product_variant_id');

        // ── Summary totals ─────────────────────────────────────────────────
        $invoiceQuery = DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at');

        if ($from) {
            $invoiceQuery->where('inv.invoice_date', '>=', $from);
        }
        if ($to) {
            $invoiceQuery->where('inv.invoice_date', '<=', $to);
        }
        if ($partyId) {
            $invoiceQuery->where('inv.party_id', $partyId);
        }
        if ($variantId) {
            $invoiceQuery->where('ii.product_variant_id', $variantId);
        }

        $totalSales = (float) (clone $invoiceQuery)
            ->selectRaw('COALESCE(SUM(ii.quantity * ii.rate - ii.discount_amount), 0) as total')
            ->value('total');

        $totalPaid = (float) DB::table('receipt_allocations as ra')
            ->join('invoices as inv', 'inv.id', '=', 'ra.invoice_id')
            ->join('receipts as r', 'r.id', '=', 'ra.receipt_id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ra.deleted_at')
            ->whereNull('r.deleted_at')
            ->when($from,    fn ($q) => $q->where('inv.invoice_date', '>=', $from))
            ->when($to,      fn ($q) => $q->where('inv.invoice_date', '<=', $to))
            ->when($partyId, fn ($q) => $q->where('inv.party_id', $partyId))
            ->selectRaw('COALESCE(SUM(ra.amount), 0) as total')
            ->value('total');

        $totalUnpaid = max(0, $totalSales - $totalPaid);

        $overdue = (float) DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->leftJoin('receipt_allocations as ra', 'ra.invoice_id', '=', 'inv.id')
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at')
            ->where('inv.due_date', '<', today())
            ->when($from,    fn ($q) => $q->where('inv.invoice_date', '>=', $from))
            ->when($to,      fn ($q) => $q->where('inv.invoice_date', '<=', $to))
            ->when($partyId, fn ($q) => $q->where('inv.party_id', $partyId))
            ->selectRaw('
                GREATEST(
                    COALESCE(SUM(ii.quantity * ii.rate - ii.discount_amount), 0)
                    - COALESCE(SUM(ra.amount), 0),
                    0
                ) as overdue
            ')
            ->value('overdue');

        // ── Per-product breakdown ───────────────────────────────────────────
        $rowQuery = DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->join('product_variants as pv', 'pv.id', '=', 'ii.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->leftJoin(
                DB::raw('(SELECT product_variant_id, SUM(quantity) as qty FROM stocks WHERE deleted_at IS NULL GROUP BY product_variant_id) as st'),
                'st.product_variant_id', '=', 'pv.id'
            )
            ->where('inv.company_id', $companyId)
            ->whereNull('inv.deleted_at')
            ->whereNull('ii.deleted_at')
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->when($from,      fn ($q) => $q->where('inv.invoice_date', '>=', $from))
            ->when($to,        fn ($q) => $q->where('inv.invoice_date', '<=', $to))
            ->when($partyId,   fn ($q) => $q->where('inv.party_id', $partyId))
            ->when($variantId, fn ($q) => $q->where('ii.product_variant_id', $variantId))
            ->groupBy('pv.id', 'pv.sku', 'p.id', 'p.name', 'b.name', 'pc.name', 'st.qty')
            ->selectRaw('
                pv.sku,
                p.name,
                b.name  AS brand,
                pc.name AS category,
                SUM(ii.quantity) AS sold_qty,
                SUM(ii.quantity * ii.rate - ii.discount_amount) AS sold_amount,
                COALESCE(st.qty, 0) AS instock_qty
            ')
            ->orderByDesc('sold_qty')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json([
            'summary' => [
                'total_sales'   => round($totalSales, 2),
                'total_paid'    => round($totalPaid, 2),
                'total_unpaid'  => round($totalUnpaid, 2),
                'overdue'       => round($overdue, 2),
            ],
            'rows' => $rowQuery,
        ]);
    }
}
