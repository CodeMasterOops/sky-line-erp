<?php

namespace App\Http\Controllers\Api\Admin\Purchase;

use Carbon\Carbon;
use App\Models\Bill;
use App\Models\Party;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class PurchaseReportController extends Controller
{
    /**
     * @Permissions("list_bill", group="bill", desc="Purchase Report Dashboard")
     */
    public function dashboard(Request $request)
    {
        $company = auth('admin')->user()?->company?->loadMissing('fiscalYear');
        $fiscalYear = $company?->fiscalYear;

        return response()->json([
            'data' => [
                'period' => [
                    'from_date' => $fiscalYear?->start_date?->toDateString(),
                    'to_date' => $fiscalYear?->end_date?->toDateString(),
                    'label' => $fiscalYear
                        ? $fiscalYear->year_name.' ('.$fiscalYear->start_date?->format('d M Y').' - '.$fiscalYear->end_date?->format('d M Y').')'
                        : 'Current fiscal year',
                ],
                'summary' => $this->buildSummaryFromDb($company?->id, $company?->fiscal_year_id),
            ],
        ]);
    }

    /**
     * @Permissions("list_bill", group="bill", desc="Purchase Report")
     */
    public function purchaseReport(Request $request)
    {
        $productVariantId = $request->filled('product_variant_id') ? (int) $request->product_variant_id : null;

        $bills = $this->buildBillQuery($request)
            ->with([
                'party',
                'discount',
                'billItems.productVariant.product',
                'billItems.productVariant.variantOptions.attribute',
                'paymentAllocations.payment',
            ])
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->get();

        $rows = $bills->map(function (Bill $bill, int $index) use ($productVariantId) {
            $totals = $this->calculateBillTotals($bill);
            $payment = $this->calculatePaymentTotals($bill, $totals['grand_total']);

            $items = $bill->billItems
                ->when($productVariantId, fn ($collection) => $collection->where('product_variant_id', $productVariantId))
                ->values()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_variant_name' => $this->productVariantLabel($item->productVariant),
                        'sku' => $item->productVariant?->sku ?? '',
                        'quantity' => (float) $item->quantity,
                        'rate' => (float) $item->rate,
                        'amount' => round(((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount + (float) $item->tax_amount, 2),
                    ];
                });

            return [
                'id' => $bill->id,
                'sn' => $index + 1,
                'bill_no' => $bill->bill_no,
                'bill_date' => $bill->bill_date,
                'due_date' => $bill->due_date,
                'party_name' => $bill->party?->name ?? '-',
                'remarks' => $bill->remarks ?? '',
                'grand_total' => $totals['grand_total'],
                'paid_total' => $payment['paid_total'],
                'due_amount' => $payment['due_amount'],
                'item_count' => max($items->count(), 1),
                'items' => $items->all(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'period' => $this->buildPeriod($request),
                'selected_party_id' => $request->party_id ?: null,
                'selected_product_variant_id' => $request->product_variant_id ?: null,
                'party_options' => $this->supplierOptions(),
                'product_variant_options' => $this->productVariantOptions(),
                'rows' => $rows,
                'summary' => $this->buildSummary($bills),
            ],
        ]);
    }

    /**
     * @Permissions("list_bill", group="bill", desc="Purchase By Item")
     */
    public function purchaseByItems(Request $request)
    {
        $rows = BillItem::query()
            ->selectRaw('product_variant_id')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(quantity * rate) as amount')
            ->selectRaw('SUM(discount_amount) as discount')
            ->selectRaw('SUM(tax_amount) as vat_amount')
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->whereNull('bill_items.deleted_at')
            ->whereNull('bills.deleted_at')
            ->where('bills.status', StatusEnum::APPROVED)
            ->whereNull('bills.voided_at')
            ->whereBetween('bills.bill_date', [
                $this->resolveFromDate($request)->toDateString(),
                $this->resolveToDate($request)->toDateString(),
            ])
            ->when($request->filled('product_variant_id'), function (Builder $query) use ($request) {
                $query->where('bill_items.product_variant_id', $request->product_variant_id);
            })
            ->groupBy('product_variant_id')
            ->orderByDesc('amount')
            ->get();

        $variants = ProductVariant::query()
            ->with(['product:id,name', 'variantOptions'])
            ->whereIn('id', $rows->pluck('product_variant_id')->filter()->all())
            ->get()
            ->keyBy('id');

        $mappedRows = $rows->map(function ($row, int $index) use ($variants) {
            $variant = $variants->get($row->product_variant_id);
            $amount = round((float) $row->amount, 2);
            $discount = round((float) $row->discount, 2);
            $netSales = round($amount - $discount, 2);
            $vatAmount = round((float) $row->vat_amount, 2);

            return [
                'id' => $row->product_variant_id ?: 'row-'.$index,
                'product_variant_id' => $row->product_variant_id,
                'product_name' => $this->productVariantLabel($variant) ?: 'Unknown Variant',
                'quantity' => round((float) $row->quantity, 2),
                'amount' => $amount,
                'discount' => $discount,
                'net_sales' => $netSales,
                'vat_amount' => $vatAmount,
                'total_amount' => round($netSales + $vatAmount, 2),
            ];
        })->values();

        return response()->json([
            'data' => [
                'period' => $this->buildPeriod($request),
                'selected_product_variant_id' => $request->product_variant_id ?: null,
                'product_variant_options' => $this->productVariantOptions(),
                'rows' => $mappedRows,
                'summary' => [
                    'quantity' => round((float) $mappedRows->sum('quantity'), 2),
                    'amount' => round((float) $mappedRows->sum('amount'), 2),
                    'discount' => round((float) $mappedRows->sum('discount'), 2),
                    'net_sales' => round((float) $mappedRows->sum('net_sales'), 2),
                    'vat_amount' => round((float) $mappedRows->sum('vat_amount'), 2),
                    'total_amount' => round((float) $mappedRows->sum('total_amount'), 2),
                ],
            ],
        ]);
    }

    private function buildBillQuery(Request $request): Builder
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $productVariantId = $request->filled('product_variant_id') ? (int) $request->product_variant_id : null;

        return Bill::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereBetween('bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), function (Builder $query) use ($request) {
                $query->where('party_id', $request->party_id);
            })
            ->when($productVariantId, function (Builder $query) use ($productVariantId) {
                $query->whereHas('billItems', function (Builder $itemQuery) use ($productVariantId) {
                    $itemQuery->where('product_variant_id', $productVariantId);
                });
            });
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

    private function buildSummaryFromDb(?int $companyId, ?int $fiscalYearId): array
    {
        $billMorphClass = (new Bill)->getMorphClass();
        $approved = StatusEnum::APPROVED->value;

        $billItemsAgg = DB::table('bill_items')
            ->selectRaw('bill_id, COALESCE(SUM(quantity * rate), 0) as subtotal, COALESCE(SUM(discount_amount), 0) as line_discount, COALESCE(SUM(tax_amount), 0) as tax')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');

        $discountsAgg = DB::table('discounts')
            ->selectRaw('discountable_id, COALESCE(SUM(amount), 0) as amount')
            ->where('discountable_type', Bill::class)
            ->groupBy('discountable_id');

        $paAgg = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->selectRaw('pa.payable_id, COALESCE(SUM(pa.amount), 0) as paid')
            ->where('pa.payable_type', $billMorphClass)
            ->where('p.status', $approved)
            ->when($companyId, fn ($q) => $q->where('p.company_id', $companyId))
            ->whereNull('pa.deleted_at')
            ->whereNull('p.deleted_at')
            ->groupBy('pa.payable_id');

        $perBill = DB::table('bills as b')
            ->selectRaw('b.due_date')
            ->selectRaw('COALESCE(bi.subtotal, 0) - COALESCE(bi.line_discount, 0) + COALESCE(bi.tax, 0) - COALESCE(d.amount, 0) as grand_total')
            ->selectRaw('COALESCE(pa.paid, 0) as paid_total')
            ->leftJoinSub($billItemsAgg, 'bi', 'bi.bill_id', '=', 'b.id')
            ->leftJoinSub($discountsAgg, 'd', 'd.discountable_id', '=', 'b.id')
            ->leftJoinSub($paAgg, 'pa', 'pa.payable_id', '=', 'b.id')
            ->where('b.status', $approved)
            ->whereNull('b.voided_at')
            ->whereNull('b.deleted_at')
            ->when($companyId, fn ($q) => $q->where('b.company_id', $companyId))
            ->when($fiscalYearId, fn ($q) => $q->where('b.fiscal_year_id', $fiscalYearId));

        $today = Carbon::today()->toDateString();

        $row = DB::query()
            ->fromSub($perBill, 'sub')
            ->selectRaw('COUNT(*) as total_bills')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(paid_total), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(CASE WHEN grand_total > paid_total THEN grand_total - paid_total ELSE 0 END), 0) as total_unpaid')
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date < ? AND grand_total > paid_total THEN grand_total - paid_total ELSE 0 END), 0) as overdue_amount', [$today])
            ->first();

        return [
            'total_amount' => round((float) $row->total_amount, 2),
            'total_paid' => round((float) $row->total_paid, 2),
            'total_unpaid' => round((float) $row->total_unpaid, 2),
            'overdue_amount' => round((float) $row->overdue_amount, 2),
            'total_bills' => (int) $row->total_bills,
        ];
    }

    private function buildSummary($bills): array
    {
        $today = Carbon::today();

        $summary = $bills->reduce(function (array $carry, Bill $bill) use ($today) {
            $totals = $this->calculateBillTotals($bill);
            $payment = $this->calculatePaymentTotals($bill, $totals['grand_total']);

            $carry['total_amount'] += $totals['grand_total'];
            $carry['total_paid'] += $payment['paid_total'];
            $carry['total_unpaid'] += $payment['due_amount'];
            $carry['total_bills']++;

            if ($bill->due_date && Carbon::parse($bill->due_date)->lt($today) && $payment['due_amount'] > 0) {
                $carry['overdue_amount'] += $payment['due_amount'];
            }

            return $carry;
        }, [
            'total_amount' => 0,
            'total_paid' => 0,
            'total_unpaid' => 0,
            'overdue_amount' => 0,
            'total_bills' => 0,
        ]);

        return [
            'total_amount' => round($summary['total_amount'], 2),
            'total_paid' => round($summary['total_paid'], 2),
            'total_unpaid' => round($summary['total_unpaid'], 2),
            'overdue_amount' => round($summary['overdue_amount'], 2),
            'total_bills' => $summary['total_bills'],
        ];
    }

    private function calculateBillTotals(Bill $bill): array
    {
        $subtotal = 0;
        $discountTotal = 0;
        $taxTotal = 0;

        foreach ($bill->billItems as $item) {
            $subtotal += (float) $item->quantity * (float) $item->rate;
            $discountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $orderDiscount = (float) ($bill->discount?->amount ?? 0);
        $grandTotal = $subtotal - $discountTotal - $orderDiscount + $taxTotal;

        return [
            'grand_total' => round($grandTotal, 2),
        ];
    }

    private function calculatePaymentTotals(Bill $bill, float $grandTotal): array
    {
        $paidTotal = 0;

        foreach ($bill->paymentAllocations as $allocation) {
            if ($allocation->payment && $allocation->payment->status === StatusEnum::APPROVED) {
                $paidTotal += (float) $allocation->amount;
            }
        }

        $paidTotal = round($paidTotal, 2);

        return [
            'paid_total' => $paidTotal,
            'due_amount' => round(max($grandTotal - $paidTotal, 0), 2),
        ];
    }

    private function supplierOptions(): array
    {
        return Party::query()
            ->where('type', PartyTypeEnum::SUPPLIER)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Party $party) => [
                'id' => (string) $party->id,
                'name' => $party->name,
            ])
            ->all();
    }

    private function productVariantOptions(): array
    {
        return ProductVariant::query()
            ->with(['product:id,name', 'variantOptions'])
            ->orderByDesc('id')
            ->get()
            ->map(function (ProductVariant $variant) {
                return [
                    'id' => (string) $variant->id,
                    'name' => $this->productVariantLabel($variant),
                ];
            })
            ->all();
    }

    private function productVariantLabel(?ProductVariant $variant): string
    {
        if (! $variant) {
            return '';
        }

        $productName = $variant->product?->name ?? '';
        $variantLabel = $variant->variant_label ?? '';

        if ($productName && $variantLabel) {
            return $productName.' ('.$variantLabel.')';
        }

        if ($productName) {
            return $productName;
        }

        if ($variantLabel) {
            return $variantLabel;
        }

        return $variant->sku ? 'Variant '.$variant->sku : 'Variant #'.$variant->id;
    }

    private function resolveFromDate(Request $request): Carbon
    {
        return $request->filled('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : now()->startOfMonth();
    }

    private function resolveToDate(Request $request): Carbon
    {
        return $request->filled('to_date')
            ? Carbon::parse($request->to_date)->endOfDay()
            : now()->endOfDay();
    }
}
