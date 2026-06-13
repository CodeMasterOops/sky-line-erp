<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use Carbon\Carbon;
use App\Models\Party;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class SalesReportController extends Controller
{
    /**
     * @Permissions("sales_report_dashboard", group="sales_report", desc="Sales Report Dashboard")
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
                'summary' => $this->buildSummaryFromDb($company?->fiscal_year_id),
            ],
        ]);
    }

    /**
     * @Permissions("sales_summary_report", group="sales_report", desc="Sales Report")
     */
    public function salesReport(Request $request)
    {
        $productVariantId = $request->filled('product_variant_id') ? (int) $request->product_variant_id : null;

        $invoices = $this->buildInvoiceQuery($request)
            ->with([
                'party',
                'discount',
                'invoiceItems.productVariant.product',
                'invoiceItems.productVariant.variantOptions.attribute',
                'receiptAllocations.receipt',
            ])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice, int $index) use ($productVariantId) {
            $totals = $this->calculateInvoiceTotals($invoice);
            $payment = $this->calculatePaymentTotals($invoice, $totals['grand_total']);

            $items = $invoice->invoiceItems
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
                'id' => $invoice->id,
                'sn' => $index + 1,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'party_name' => $invoice->party?->name ?? '-',
                'remarks' => $invoice->remarks ?? '',
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_total' => $totals['tax_total'],
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
                'party_options' => $this->partyOptions(),
                'product_variant_options' => $this->productVariantOptions(),
                'rows' => $rows,
                'summary' => $this->buildSummary($invoices),
            ],
        ]);
    }

    /**
     * @Permissions("sales_by_item_report", group="sales_report", desc="Sales By Item")
     */
    public function salesByItems(Request $request)
    {
        $rows = InvoiceItem::query()
            ->selectRaw('product_variant_id')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(quantity * rate) as amount')
            ->selectRaw('SUM(discount_amount) as discount')
            ->selectRaw('SUM(tax_amount) as vat_amount')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->whereNull('invoice_items.deleted_at')
            ->whereNull('invoices.deleted_at')
            ->where('invoices.status', StatusEnum::APPROVED)
            ->whereNull('invoices.voided_at')
            ->whereBetween('invoices.invoice_date', [
                $this->resolveFromDate($request)->toDateString(),
                $this->resolveToDate($request)->toDateString(),
            ])
            ->when($request->filled('product_variant_id'), function (Builder $query) use ($request) {
                $query->where('invoice_items.product_variant_id', $request->product_variant_id);
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

    private function buildInvoiceQuery(Request $request): Builder
    {
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();
        $productVariantId = $request->filled('product_variant_id') ? (int) $request->product_variant_id : null;

        return Invoice::query()
            ->where('status', StatusEnum::APPROVED)
            ->whereNull('voided_at')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->when($request->filled('party_id'), function (Builder $query) use ($request) {
                $query->where('party_id', $request->party_id);
            })
            ->when($productVariantId, function (Builder $query) use ($productVariantId) {
                $query->whereHas('invoiceItems', function (Builder $itemQuery) use ($productVariantId) {
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

    private function buildSummaryFromDb(?int $fiscalYearId): array
    {
        $today = Carbon::today()->toDateString();

        $itemsSub = DB::table('invoice_items')
            ->selectRaw('invoice_id, SUM(quantity * rate) - SUM(discount_amount) + SUM(tax_amount) as net_total')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as paid_total')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereNull('receipts.deleted_at')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->groupBy('receipt_allocations.invoice_id');

        $rows = DB::table('invoices')
            ->leftJoinSub($itemsSub, 'item_totals', fn ($j) => $j->on('invoices.id', '=', 'item_totals.invoice_id'))
            ->leftJoinSub($paidSub, 'paid_totals', fn ($j) => $j->on('invoices.id', '=', 'paid_totals.invoice_id'))
            ->leftJoin('discounts', function ($j) {
                $j->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', Invoice::class);
            })
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->when($fiscalYearId, fn ($q) => $q->where('invoices.fiscal_year_id', $fiscalYearId))
            ->select([
                'invoices.id',
                'invoices.due_date',
                DB::raw('COALESCE(item_totals.net_total, 0) - COALESCE(discounts.amount, 0) as grand_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get();

        $totalAmount = 0.0;
        $totalPaid = 0.0;
        $overdueAmount = 0.0;
        $totalInvoices = count($rows);

        foreach ($rows as $row) {
            $grand = (float) $row->grand_total;
            $paid = (float) $row->paid_total;
            $due = max($grand - $paid, 0);

            $totalAmount += $grand;
            $totalPaid += $paid;

            if ($row->due_date && $row->due_date < $today && $due > 0) {
                $overdueAmount += $due;
            }
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_unpaid' => round($totalAmount - $totalPaid, 2),
            'overdue_amount' => round($overdueAmount, 2),
            'total_invoices' => $totalInvoices,
        ];
    }

    private function buildSummary($invoices): array
    {
        $today = Carbon::today();

        $summary = $invoices->reduce(function (array $carry, Invoice $invoice) use ($today) {
            $totals = $this->calculateInvoiceTotals($invoice);
            $payment = $this->calculatePaymentTotals($invoice, $totals['grand_total']);

            $carry['total_amount'] += $totals['grand_total'];
            $carry['total_paid'] += $payment['paid_total'];
            $carry['total_unpaid'] += $payment['due_amount'];
            $carry['total_invoices']++;

            if ($invoice->due_date && Carbon::parse($invoice->due_date)->lt($today) && $payment['due_amount'] > 0) {
                $carry['overdue_amount'] += $payment['due_amount'];
            }

            return $carry;
        }, [
            'total_amount' => 0,
            'total_paid' => 0,
            'total_unpaid' => 0,
            'overdue_amount' => 0,
            'total_invoices' => 0,
        ]);

        return [
            'total_amount' => round($summary['total_amount'], 2),
            'total_paid' => round($summary['total_paid'], 2),
            'total_unpaid' => round($summary['total_unpaid'], 2),
            'overdue_amount' => round($summary['overdue_amount'], 2),
            'total_invoices' => $summary['total_invoices'],
        ];
    }

    private function calculateInvoiceTotals(Invoice $invoice): array
    {
        $subtotal = 0;
        $lineDiscountTotal = 0;
        $taxTotal = 0;

        foreach ($invoice->invoiceItems as $item) {
            $subtotal += (float) $item->quantity * (float) $item->rate;
            $lineDiscountTotal += (float) $item->discount_amount;
            $taxTotal += (float) $item->tax_amount;
        }

        $orderDiscountAmount = (float) ($invoice->discount?->amount ?? 0);
        $discountTotal = $lineDiscountTotal + $orderDiscountAmount;
        $grandTotal = $subtotal - $discountTotal + $taxTotal;

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
        ];
    }

    private function calculatePaymentTotals(Invoice $invoice, float $grandTotal): array
    {
        $paidTotal = 0;

        foreach ($invoice->receiptAllocations as $allocation) {
            if ($allocation->receipt && $allocation->receipt->status === StatusEnum::APPROVED) {
                $paidTotal += (float) $allocation->amount;
            }
        }

        $paidTotal = round($paidTotal, 2);

        return [
            'paid_total' => $paidTotal,
            'due_amount' => round(max($grandTotal - $paidTotal, 0), 2),
        ];
    }

    private function partyOptions(): array
    {
        return Party::query()
            ->where('type', PartyTypeEnum::CUSTOMER)
            ->orderBy('name')
            ->limit(500)
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
            ->limit(500)
            ->get()
            ->map(function (ProductVariant $variant) {
                return [
                    'id' => (string) $variant->id,
                    'name' => $this->productVariantLabel($variant) ?: ($variant->sku ? 'Variant '.$variant->sku : 'Variant #'.$variant->id),
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
