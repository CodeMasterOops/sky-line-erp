<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;

        $from      = $request->input('from');
        $to        = $request->input('to');
        $partyId   = $request->input('party_id');
        $variantId = $request->input('product_variant_id');

        // ── Summary totals ─────────────────────────────────────────────────
        $totalPurchase = (float) DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('bi.deleted_at')
            ->when($from,      fn ($q) => $q->where('b.bill_date', '>=', $from))
            ->when($to,        fn ($q) => $q->where('b.bill_date', '<=', $to))
            ->when($partyId,   fn ($q) => $q->where('b.party_id', $partyId))
            ->when($variantId, fn ($q) => $q->where('bi.product_variant_id', $variantId))
            ->selectRaw('COALESCE(SUM(bi.quantity * bi.rate - bi.discount_amount), 0) as total')
            ->value('total');

        // Paid via payment vouchers (polymorphic payable = Bill)
        $totalPaid = (float) DB::table('payment_allocations as pa')
            ->join('bills as b', 'b.id', '=', 'pa.payable_id')
            ->where('pa.payable_type', \App\Models\Bill::class)
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('pa.deleted_at')
            ->when($from,    fn ($q) => $q->where('b.bill_date', '>=', $from))
            ->when($to,      fn ($q) => $q->where('b.bill_date', '<=', $to))
            ->when($partyId, fn ($q) => $q->where('b.party_id', $partyId))
            ->selectRaw('COALESCE(SUM(pa.amount), 0) as total')
            ->value('total');

        $totalUnpaid = max(0, $totalPurchase - $totalPaid);

        $overdue = (float) DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->leftJoin('payment_allocations as pa', fn ($j) =>
                $j->on('pa.payable_id', '=', 'b.id')
                  ->where('pa.payable_type', \App\Models\Bill::class)
                  ->whereNull('pa.deleted_at'))
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('bi.deleted_at')
            ->where('b.due_date', '<', today())
            ->when($from,    fn ($q) => $q->where('b.bill_date', '>=', $from))
            ->when($to,      fn ($q) => $q->where('b.bill_date', '<=', $to))
            ->when($partyId, fn ($q) => $q->where('b.party_id', $partyId))
            ->selectRaw('
                GREATEST(
                    COALESCE(SUM(bi.quantity * bi.rate - bi.discount_amount), 0)
                    - COALESCE(SUM(pa.amount), 0),
                    0
                ) as overdue
            ')
            ->value('overdue');

        // ── Per-product breakdown ───────────────────────────────────────────
        $rows = DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->join('product_variants as pv', 'pv.id', '=', 'bi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as br', 'br.id', '=', 'p.brand_id')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->leftJoin(
                DB::raw('(SELECT product_variant_id, SUM(quantity) as qty FROM stocks WHERE deleted_at IS NULL GROUP BY product_variant_id) as st'),
                'st.product_variant_id', '=', 'pv.id'
            )
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('bi.deleted_at')
            ->whereNull('pv.deleted_at')
            ->whereNull('p.deleted_at')
            ->when($from,      fn ($q) => $q->where('b.bill_date', '>=', $from))
            ->when($to,        fn ($q) => $q->where('b.bill_date', '<=', $to))
            ->when($partyId,   fn ($q) => $q->where('b.party_id', $partyId))
            ->when($variantId, fn ($q) => $q->where('bi.product_variant_id', $variantId))
            ->groupBy('pv.id', 'pv.sku', 'p.id', 'p.name', 'br.name', 'pc.name', 'st.qty')
            ->selectRaw('
                pv.sku,
                p.name,
                br.name AS brand,
                pc.name AS category,
                SUM(bi.quantity) AS purchased_qty,
                SUM(bi.quantity * bi.rate - bi.discount_amount) AS purchase_amount,
                COALESCE(st.qty, 0) AS instock_qty
            ')
            ->orderByDesc('purchased_qty')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return response()->json([
            'summary' => [
                'total_purchase' => round($totalPurchase, 2),
                'total_paid'     => round($totalPaid, 2),
                'total_unpaid'   => round($totalUnpaid, 2),
                'overdue'        => round($overdue, 2),
            ],
            'rows' => $rows,
        ]);
    }
}
