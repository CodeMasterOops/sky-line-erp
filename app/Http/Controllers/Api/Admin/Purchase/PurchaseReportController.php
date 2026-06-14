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

        $summary = $this->buildSummaryFromDb(
            fiscalYearId: $company?->fiscal_year_id,
            branchId: $request->filled('branch_id') ? (int) $request->branch_id : null,
        );

        return response()->json([
            'data' => [
                'period' => [
                    'from_date' => $fiscalYear?->start_date?->toDateString(),
                    'to_date' => $fiscalYear?->end_date?->toDateString(),
                    'label' => $fiscalYear
                        ? $fiscalYear->year_name.' ('.$fiscalYear->start_date?->format('d M Y').' - '.$fiscalYear->end_date?->format('d M Y').')'
                        : 'Current fiscal year',
                ],
                'summary' => $summary,
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
                'summary' => $this->buildSummaryFromCollection($bills),
            ],
        ]);
    }

    /**
     * @Permissions("list_bill", group="bill", desc="Supplier Ledger / AP Aging")
     */
    public function supplierLedger(Request $request)
    {
        $today = Carbon::today();
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $approvedStatus = StatusEnum::APPROVED->value;

        $itemsSub = DB::table('bill_items')
            ->selectRaw('bill_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as line_discount, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');

        $paidSub = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->selectRaw('payment_allocations.payable_id, SUM(payment_allocations.amount) as paid_total')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('payments.status', $approvedStatus)
            ->where('payment_allocations.payable_type', 'bill')
            ->groupBy('payment_allocations.payable_id');

        $bills = DB::table('bills')
            ->leftJoinSub($itemsSub, 'item_agg', fn ($j) => $j->on('bills.id', '=', 'item_agg.bill_id'))
            ->leftJoinSub($paidSub, 'paid_agg', fn ($j) => $j->on('bills.id', '=', 'paid_agg.payable_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', 'bill');
            })
            ->where('bills.status', $approvedStatus)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->whereBetween('bills.bill_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), fn ($q) => $q->where('bills.party_id', $request->party_id))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('bills.branch_id', $request->branch_id))
            ->selectRaw('
                bills.party_id,
                bills.id as bill_id,
                bills.bill_no,
                bills.bill_date,
                bills.due_date,
                COALESCE(item_agg.subtotal - item_agg.line_discount - COALESCE(discounts.amount, 0) + item_agg.tax_total, 0) as grand_total,
                COALESCE(paid_agg.paid_total, 0) as paid_total
            ')
            ->get();

        $supplierIds = $bills->pluck('party_id')->unique()->filter()->values()->all();
        $suppliers = Party::query()
            ->whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get(['id', 'name', 'pan_no'])
            ->keyBy('id');

        $grouped = $bills->groupBy('party_id');

        $rows = $grouped->map(function ($supplierBills, $partyId) use ($today, $suppliers) {
            $supplier = $suppliers->get($partyId);
            $totalBilled = 0.0;
            $totalPaid = 0.0;
            $current = 0.0;
            $days1to30 = 0.0;
            $days31to60 = 0.0;
            $days61to90 = 0.0;
            $daysOver90 = 0.0;

            foreach ($supplierBills as $bill) {
                $grand = round((float) $bill->grand_total, 2);
                $paid = round((float) $bill->paid_total, 2);
                $due = round(max($grand - $paid, 0), 2);
                $totalBilled += $grand;
                $totalPaid += $paid;

                if ($due <= 0) {
                    continue;
                }

                $dueDate = $bill->due_date ? Carbon::parse($bill->due_date) : Carbon::parse($bill->bill_date);
                $daysOverdue = (int) $dueDate->diffInDays($today, false);

                if ($daysOverdue <= 0) {
                    $current += $due;
                } elseif ($daysOverdue <= 30) {
                    $days1to30 += $due;
                } elseif ($daysOverdue <= 60) {
                    $days31to60 += $due;
                } elseif ($daysOverdue <= 90) {
                    $days61to90 += $due;
                } else {
                    $daysOver90 += $due;
                }
            }

            $totalDue = round(max($totalBilled - $totalPaid, 0), 2);

            return [
                'party_id' => $partyId,
                'supplier_name' => $supplier?->name ?? 'Unknown',
                'pan_no' => $supplier?->pan_no ?? '',
                'total_billed' => round($totalBilled, 2),
                'total_paid' => round($totalPaid, 2),
                'total_due' => $totalDue,
                'current' => round($current, 2),
                'days_1_30' => round($days1to30, 2),
                'days_31_60' => round($days31to60, 2),
                'days_61_90' => round($days61to90, 2),
                'days_over_90' => round($daysOver90, 2),
            ];
        })->values()->sortByDesc('total_due')->values();

        $totals = [
            'total_billed' => round($rows->sum('total_billed'), 2),
            'total_paid' => round($rows->sum('total_paid'), 2),
            'total_due' => round($rows->sum('total_due'), 2),
            'current' => round($rows->sum('current'), 2),
            'days_1_30' => round($rows->sum('days_1_30'), 2),
            'days_31_60' => round($rows->sum('days_31_60'), 2),
            'days_61_90' => round($rows->sum('days_61_90'), 2),
            'days_over_90' => round($rows->sum('days_over_90'), 2),
        ];

        return response()->json([
            'data' => [
                'period' => $this->buildPeriod($request),
                'party_options' => $this->supplierOptions(),
                'rows' => $rows->all(),
                'totals' => $totals,
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
            ->when($request->filled('branch_id'), function (Builder $query) use ($request) {
                $query->where('bills.branch_id', $request->branch_id);
            })
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
            ->when($request->filled('branch_id'), function (Builder $query) use ($request) {
                $query->where('branch_id', $request->branch_id);
            })
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

    private function buildSummaryFromCollection($bills): array
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

    private function buildSummaryFromDb(?int $fiscalYearId, ?int $branchId): array
    {
        $today = Carbon::today()->toDateString();
        $approvedStatus = StatusEnum::APPROVED->value;

        $itemsSub = DB::table('bill_items')
            ->selectRaw('bill_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as line_discount, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');

        $paidSub = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->selectRaw('payment_allocations.payable_id, SUM(payment_allocations.amount) as paid_total')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('payments.status', $approvedStatus)
            ->where('payment_allocations.payable_type', 'bill')
            ->groupBy('payment_allocations.payable_id');

        $rows = DB::table('bills')
            ->leftJoinSub($itemsSub, 'item_agg', fn ($j) => $j->on('bills.id', '=', 'item_agg.bill_id'))
            ->leftJoinSub($paidSub, 'paid_agg', fn ($j) => $j->on('bills.id', '=', 'paid_agg.payable_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', 'bill');
            })
            ->where('bills.status', $approvedStatus)
            ->whereNull('bills.voided_at')
            ->whereNull('bills.deleted_at')
            ->when($fiscalYearId, fn ($q) => $q->where('bills.fiscal_year_id', $fiscalYearId))
            ->when($branchId, fn ($q) => $q->where('bills.branch_id', $branchId))
            ->selectRaw('
                COUNT(bills.id) as total_bills,
                COALESCE(SUM(item_agg.subtotal - item_agg.line_discount - COALESCE(discounts.amount, 0) + item_agg.tax_total), 0) as total_amount,
                COALESCE(SUM(paid_agg.paid_total), 0) as total_paid,
                COALESCE(SUM(CASE WHEN bills.due_date IS NOT NULL AND bills.due_date < ? AND GREATEST(item_agg.subtotal - item_agg.line_discount - COALESCE(discounts.amount, 0) + item_agg.tax_total - COALESCE(paid_agg.paid_total, 0), 0) > 0 THEN GREATEST(item_agg.subtotal - item_agg.line_discount - COALESCE(discounts.amount, 0) + item_agg.tax_total - COALESCE(paid_agg.paid_total, 0), 0) ELSE 0 END), 0) as overdue_amount
            ', [$today])
            ->first();

        $totalAmount = round((float) $rows->total_amount, 2);
        $totalPaid = round((float) $rows->total_paid, 2);

        return [
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_unpaid' => round(max($totalAmount - $totalPaid, 0), 2),
            'overdue_amount' => round((float) $rows->overdue_amount, 2),
            'total_bills' => (int) $rows->total_bills,
        ];
    }

    private function calculateBillTotals(Bill $bill): array
    {
        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($bill->billItems as $item) {
            $subtotal += (float) $item->quantity * (float) $item->rate;
            $lineDiscountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $orderDiscount = (float) ($bill->discount?->amount ?? 0);
        $grandTotal = $subtotal - $lineDiscountTotal - $orderDiscount + $taxTotal;

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
