<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use Carbon\Carbon;
use App\Models\Party;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\InvoiceItem;
use App\Enums\PartyTypeEnum;
use App\Models\TdsDeduction;
use Illuminate\Http\Request;
use App\Enums\TaxLineTypeEnum;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use App\Models\ReceiptAllocation;
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

    /**
     * @Permissions("sales_aging_report", group="sales_report", desc="Sales Aging Report")
     */
    public function aging(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;
        $asOf = $request->filled('as_of_date') ? Carbon::parse($request->as_of_date) : Carbon::today();

        $invoices = Invoice::with(['party', 'invoiceItems', 'discount'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->get()
            ->filter(function (Invoice $invoice) {
                $totals = $this->calculateInvoiceTotals($invoice);
                $paid = $invoice->receiptAllocations()->sum('amount');

                return round($totals['grand_total'] - $paid, 2) > 0;
            });

        $rows = $invoices->map(function (Invoice $invoice) use ($asOf) {
            $totals = $this->calculateInvoiceTotals($invoice);
            $paid = round((float) $invoice->receiptAllocations()->sum('amount'), 2);
            $outstanding = round($totals['grand_total'] - $paid, 2);
            $dueDate = $invoice->due_date
                ? Carbon::parse($invoice->due_date)
                : Carbon::parse($invoice->invoice_date)->addDays(30);
            $daysOverdue = (int) max(0, $asOf->diffInDays($dueDate, false) * -1);

            return [
                'invoice_no' => $invoice->invoice_no,
                'party_id' => $invoice->party_id,
                'party_name' => $invoice->party?->name ?? '-',
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $dueDate->toDateString(),
                'days_overdue' => $daysOverdue,
                'outstanding' => $outstanding,
                'bucket' => $this->agingBucket($daysOverdue),
            ];
        })->sortByDesc('days_overdue')->values();

        return response()->json([
            'data' => [
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
            ],
        ]);
    }

    /**
     * @Permissions("party_statement_report", group="sales_report", desc="Party Statement")
     */
    public function partyStatement(Request $request)
    {
        $request->validate([
            'party_id' => ['required', 'exists:parties,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $companyId = auth('admin')->user()->company_id;
        $partyId = (int) $request->party_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $party = Party::findOrFail($partyId);

        $invoices = Invoice::with(['invoiceItems', 'discount'])
            ->where('company_id', $companyId)
            ->where('party_id', $partyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $receipts = ReceiptAllocation::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->join('invoices', 'invoices.id', '=', 'receipt_allocations.invoice_id')
            ->where('receipts.company_id', $companyId)
            ->where('invoices.party_id', $partyId)
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->whereBetween('receipts.receipt_date', [$fromDate, $toDate])
            ->select([
                'receipt_allocations.*',
                'receipts.receipt_no',
                'receipts.receipt_date',
                'invoices.invoice_no as ref_invoice_no',
            ])
            ->orderBy('receipts.receipt_date')
            ->orderBy('receipts.id')
            ->get();

        $entries = collect();

        foreach ($invoices as $invoice) {
            $totals = $this->calculateInvoiceTotals($invoice);
            $entries->push([
                'date' => $invoice->invoice_date,
                'type' => 'invoice',
                'reference' => $invoice->invoice_no,
                'description' => 'Invoice',
                'debit' => $totals['grand_total'],
                'credit' => 0,
            ]);
        }

        foreach ($receipts as $receipt) {
            $entries->push([
                'date' => $receipt->receipt_date,
                'type' => 'receipt',
                'reference' => $receipt->receipt_no,
                'description' => 'Receipt for '.$receipt->ref_invoice_no,
                'debit' => 0,
                'credit' => (float) $receipt->amount,
            ]);
        }

        $entries = $entries->sortBy(['date', 'type'])->values();

        $runningBalance = 0.0;
        $rows = $entries->map(function (array $entry) use (&$runningBalance) {
            $runningBalance += $entry['debit'] - $entry['credit'];

            return array_merge($entry, ['balance' => round($runningBalance, 2)]);
        });

        return response()->json([
            'data' => [
                'party' => ['id' => $party->id, 'name' => $party->name, 'pan' => $party->pan ?? ''],
                'period' => $this->buildPeriod($request),
                'rows' => $rows->all(),
                'summary' => [
                    'total_invoiced' => round($rows->sum('debit'), 2),
                    'total_received' => round($rows->sum('credit'), 2),
                    'closing_balance' => round($runningBalance, 2),
                ],
            ],
        ]);
    }

    /**
     * @Permissions("vat_register_report", group="sales_report", desc="VAT Register (D1/D2)")
     */
    public function vatRegister(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $invoices = Invoice::with(['party', 'invoiceItems', 'discount'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        $taxable = collect();
        $exempt = collect();
        $zeroRated = collect();

        foreach ($invoices as $invoice) {
            $taxableAmount = 0.0;
            $vatAmount = 0.0;
            $exemptAmount = 0.0;
            $zeroRatedAmount = 0.0;
            $orderDiscountAmount = (float) ($invoice->discount?->amount ?? 0);
            $itemCount = $invoice->invoiceItems->count();
            $perItemDiscount = $itemCount > 0 ? $orderDiscountAmount / $itemCount : 0;

            foreach ($invoice->invoiceItems as $item) {
                $lineBase = ((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount - $perItemDiscount;
                $lineType = $item->tax_line_type ?? TaxLineTypeEnum::TAXABLE;

                if ($lineType === TaxLineTypeEnum::TAXABLE || $lineType?->value === 'taxable') {
                    $taxableAmount += $lineBase;
                    $vatAmount += (float) $item->tax_amount;
                } elseif ($lineType === TaxLineTypeEnum::EXEMPT || $lineType?->value === 'exempt') {
                    $exemptAmount += $lineBase;
                } elseif ($lineType === TaxLineTypeEnum::ZERO_RATED || $lineType?->value === 'zero_rated') {
                    $zeroRatedAmount += $lineBase;
                }
            }

            $row = [
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date,
                'party_name' => $invoice->party?->name ?? '-',
                'party_pan' => $invoice->party?->pan ?? '-',
            ];

            if ($taxableAmount > 0 || $vatAmount > 0) {
                $taxable->push(array_merge($row, [
                    'taxable_amount' => round($taxableAmount, 2),
                    'vat_amount' => round($vatAmount, 2),
                ]));
            }

            if ($exemptAmount > 0) {
                $exempt->push(array_merge($row, ['amount' => round($exemptAmount, 2)]));
            }

            if ($zeroRatedAmount > 0) {
                $zeroRated->push(array_merge($row, ['amount' => round($zeroRatedAmount, 2)]));
            }
        }

        return response()->json([
            'data' => [
                'period' => $this->buildPeriod($request),
                'taxable_sales' => $taxable->all(),
                'exempt_sales' => $exempt->all(),
                'zero_rated_sales' => $zeroRated->all(),
                'totals' => [
                    'taxable' => round($taxable->sum('taxable_amount'), 2),
                    'vat' => round($taxable->sum('vat_amount'), 2),
                    'exempt' => round($exempt->sum('amount'), 2),
                    'zero_rated' => round($zeroRated->sum('amount'), 2),
                ],
            ],
        ]);
    }

    /**
     * @Permissions("tds_register_report", group="sales_report", desc="TDS Register")
     */
    public function tdsRegister(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;
        $fromDate = $this->resolveFromDate($request)->toDateString();
        $toDate = $this->resolveToDate($request)->toDateString();

        $deductions = TdsDeduction::with(['party', 'fiscalYear'])
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$fromDate.' 00:00:00', $toDate.' 23:59:59'])
            ->orderBy('created_at')
            ->get();

        $rows = $deductions->map(function (TdsDeduction $d, int $index) {
            return [
                'sn' => $index + 1,
                'party_name' => $d->party?->name ?? '-',
                'party_pan' => $d->party?->pan ?? '-',
                'tds_category' => $d->tds_category?->value ?? '-',
                'tds_category_label' => $d->tds_category?->label() ?? '-',
                'base_amount' => (float) $d->base_amount,
                'tds_rate' => (float) $d->tds_rate,
                'tds_amount' => (float) $d->tds_amount,
                'period_month' => $d->period_month,
                'date' => $d->created_at?->toDateString(),
            ];
        })->values();

        $byParty = $deductions->groupBy('party_id')->map(function ($group) {
            $party = $group->first()?->party;

            return [
                'party_name' => $party?->name ?? '-',
                'party_pan' => $party?->pan ?? '-',
                'total_base' => round($group->sum('base_amount'), 2),
                'total_tds' => round($group->sum('tds_amount'), 2),
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'period' => $this->buildPeriod($request),
                'rows' => $rows->all(),
                'by_party' => $byParty,
                'summary' => [
                    'total_base_amount' => round($deductions->sum('base_amount'), 2),
                    'total_tds_amount' => round($deductions->sum('tds_amount'), 2),
                ],
            ],
        ]);
    }

    /**
     * @Permissions("customer_outstanding_report", group="sales_report", desc="Customer Outstanding Report")
     */
    public function outstanding(Request $request)
    {
        $companyId = auth('admin')->user()->company_id;
        $asOf = $request->filled('as_of_date') ? Carbon::parse($request->as_of_date)->toDateString() : Carbon::today()->toDateString();

        $invoices = Invoice::with(['party', 'invoiceItems', 'discount', 'receiptAllocations.receipt'])
            ->where('company_id', $companyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereNull('voided_at')
            ->where('invoice_date', '<=', $asOf)
            ->get();

        $grouped = $invoices->groupBy('party_id');

        $rows = $grouped->map(function ($partyInvoices) {
            $party = $partyInvoices->first()?->party;
            $totalInvoiced = 0.0;
            $totalReceived = 0.0;
            $totalTdsDeducted = 0.0;
            $oldestUnpaid = null;

            foreach ($partyInvoices as $invoice) {
                $totals = $this->calculateInvoiceTotals($invoice);
                $totalInvoiced += $totals['grand_total'];

                $paid = 0.0;
                foreach ($invoice->receiptAllocations as $allocation) {
                    if ($allocation->receipt?->status === StatusEnum::APPROVED) {
                        $paid += (float) $allocation->amount;
                        $totalTdsDeducted += (float) ($allocation->tds_deducted ?? 0);
                    }
                }
                $totalReceived += $paid;

                $due = $totals['grand_total'] - $paid;
                if ($due > 0) {
                    $date = $invoice->invoice_date;
                    if ($oldestUnpaid === null || $date < $oldestUnpaid) {
                        $oldestUnpaid = $date;
                    }
                }
            }

            return [
                'party_id' => $party?->id,
                'party_name' => $party?->name ?? '-',
                'party_pan' => $party?->pan ?? '-',
                'total_invoiced' => round($totalInvoiced, 2),
                'total_received' => round($totalReceived, 2),
                'tds_deducted' => round($totalTdsDeducted, 2),
                'net_outstanding' => round($totalInvoiced - $totalReceived, 2),
                'oldest_unpaid_date' => $oldestUnpaid,
            ];
        })->values()->filter(fn ($r) => $r['net_outstanding'] > 0)->sortByDesc('net_outstanding')->values();

        return response()->json([
            'data' => [
                'as_of' => $asOf,
                'rows' => $rows->all(),
                'summary' => [
                    'total_invoiced' => round($rows->sum('total_invoiced'), 2),
                    'total_received' => round($rows->sum('total_received'), 2),
                    'tds_deducted' => round($rows->sum('tds_deducted'), 2),
                    'net_outstanding' => round($rows->sum('net_outstanding'), 2),
                ],
            ],
        ]);
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
