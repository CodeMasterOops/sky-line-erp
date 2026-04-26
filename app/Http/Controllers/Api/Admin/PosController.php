<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChangeTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\StatusEnum;
use App\Models\AccountSetting;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PosHeldOrder;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Warehouse;
use App\Services\Accounting\InvoiceGlPostingService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Nepal\NepaliDateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Jobs\SyncInvoiceToIrdJob;

class PosController extends Controller
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InvoiceGlPostingService $invoiceGl,
        private NepaliDateService $nepaliDate,
    ) {}

    /**
     * Products list optimised for POS – all variants with stock per warehouse.
     */
    public function products(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id');
        $categoryId = $request->integer('category_id');
        $search = trim((string) $request->get('search', ''));

        $query = Product::with([
            'productCategory:id,name',
            'variants' => function ($q) use ($warehouseId) {
                $q->with([
                    'stocks' => fn ($sq) => $sq->when($warehouseId, fn ($s) => $s->where('warehouse_id', $warehouseId)),
                ]);
            },
        ]);

        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });
        }

        $products = $query->latest('id')->get();

        $data = $products->flatMap(function ($product) {
            return $product->variants->map(function ($variant) use ($product) {
                $stock = $variant->stocks->sum('quantity');
                return [
                    'id'            => $variant->id,
                    'product_id'    => $product->id,
                    'name'          => $product->name . ($variant->variant_label ? ' - ' . $variant->variant_label : ''),
                    'sku'           => $variant->sku,
                    'sales_price'   => $variant->sales_price ?? 0,
                    'purchase_price'=> $variant->purchase_price ?? 0,
                    'image'         => $product->image,
                    'category_id'   => $product->product_category_id,
                    'category_name' => $product->productCategory?->name ?? '',
                    'unit_id'       => $product->unit_id,
                    'stock'         => (float) $stock,
                    'is_default'    => $variant->is_default,
                ];
            });
        });

        return response()->json(['data' => $data->values()]);
    }

    /**
     * Customers (party type = customer) for POS customer selector.
     */
    public function customers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = Party::where('type', PartyTypeEnum::CUSTOMER)->where('is_active', true);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            });
        }

        $customers = $query->select('id', 'name', 'phone', 'email')->limit(50)->get();

        return response()->json(['data' => $customers]);
    }

    /**
     * Warehouses for POS store selector.
     */
    public function warehouses()
    {
        $warehouses = Warehouse::select('id', 'name', 'code')->get();

        return response()->json(['data' => $warehouses]);
    }

    /**
     * Today's sales summary.
     */
    public function todaySummary()
    {
        $today = now()->toDateString();

        $invoiceIds = Invoice::where('status', StatusEnum::APPROVED)
            ->whereDate('invoice_date', $today)
            ->pluck('id');

        $totals = DB::table('invoice_items')
            ->whereIn('invoice_id', $invoiceIds)
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(DISTINCT invoice_id) as sale_count,
                SUM(quantity * rate) as subtotal,
                SUM(discount_amount) as discount_total,
                SUM(tax_amount) as tax_total
            ')
            ->first();

        $subtotal = (float) ($totals->subtotal ?? 0);
        $discountTotal = (float) ($totals->discount_total ?? 0);
        $taxTotal = (float) ($totals->tax_total ?? 0);
        $saleTotal = $subtotal - $discountTotal + $taxTotal;

        // Profit = revenue - COGS (purchase_price * qty)
        $cogsSub = DB::table('invoice_items as ii')
            ->join('product_variants as pv', 'pv.id', '=', 'ii.product_variant_id')
            ->whereIn('ii.invoice_id', $invoiceIds)
            ->whereNull('ii.deleted_at')
            ->selectRaw('SUM(ii.quantity * pv.purchase_price) as cogs')
            ->first();

        $cogs = (float) ($cogsSub->cogs ?? 0);
        $profit = $saleTotal - $cogs;

        return response()->json([
            'data' => [
                'sale_count'  => (int) ($totals->sale_count ?? 0),
                'sale_total'  => round($saleTotal, 2),
                'cogs'        => round($cogs, 2),
                'profit'      => round($profit, 2),
            ],
        ]);
    }

    /**
     * Complete a POS sale: create approved invoice + approved receipt in one transaction.
     *
     * Request body:
     * {
     *   party_id: int|null,
     *   warehouse_id: int,
     *   payment_method: 'cash'|'card'|'scan',
     *   items: [{product_variant_id, unit_id, quantity, rate, tax_id, tax_amount, discount_amount}],
     *   remarks: string|null
     * }
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'warehouse_id'          => ['required', 'integer'],
            'payment_method'        => ['required', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity'      => ['required', 'numeric', 'min:0.001'],
            'items.*.rate'          => ['required', 'numeric', 'min:0'],
            'items.*.unit_id'       => ['nullable', 'integer'],
            'items.*.tax_id'        => ['nullable', 'integer'],
            'items.*.tax_amount'    => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'party_id'              => ['nullable', 'integer'],
            'remarks'               => ['nullable', 'string'],
        ]);

        $user = auth('admin')->user();
        $company = $user->company;
        $fiscalYearId = $company->fiscal_year_id;
        $today = now()->toDateString();

        $accountSetting = AccountSetting::first();
        $accountId = $this->resolveAccountId($request->payment_method, $accountSetting);

        $invoiceCount = Invoice::withTrashed()->where('fiscal_year_id', $fiscalYearId)->count();
        $yearCode = $company->fiscalYear?->year_code;
        $suffix = $yearCode ? '/' . $yearCode : '';
        $invoiceNo = 'INV-' . ($invoiceCount + 1) . $suffix;

        try {
            $invoice = DB::transaction(function () use ($request, $user, $company, $fiscalYearId, $today, $invoiceNo, $accountId, $accountSetting) {
                $invoiceDateBs = null;
                try {
                    $bs = $this->nepaliDate->adToBs($today);
                    $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
                } catch (\Throwable) {}

                $invoice = Invoice::create([
                    'fiscal_year_id'   => $fiscalYearId,
                    'party_id'         => $request->party_id ?? null,
                    'invoice_no'       => $invoiceNo,
                    'invoice_date'     => $today,
                    'invoice_date_bs'  => $invoiceDateBs,
                    'due_date'         => $today,
                    'remarks'          => $request->remarks ?? 'POS Sale',
                    'create_user_id'   => $user->id,
                    'approve_user_id'  => $user->id,
                    'approved_at'      => now(),
                    'status'           => StatusEnum::APPROVED->value,
                ]);

                $items = collect($request->items)->map(fn ($item) => [
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id'       => $request->warehouse_id,
                    'unit_id'            => $item['unit_id'] ?? null,
                    'quantity'           => $item['quantity'],
                    'rate'               => $item['rate'],
                    'tax_id'             => $item['tax_id'] ?? null,
                    'tax_amount'         => $item['tax_amount'] ?? 0,
                    'discount_amount'    => $item['discount_amount'] ?? 0,
                    'tax_line_type'      => 'taxable',
                ])->all();

                $invoice->invoiceItems()->createMany($items);
                $invoice->refresh()->loadMissing('invoiceItems');

                // Deduct inventory
                foreach ($invoice->invoiceItems as $item) {
                    if ((int) $item->quantity > 0) {
                        $this->inventoryIssue->issue(
                            $company,
                            $invoice,
                            $item->product_variant_id,
                            $item->warehouse_id,
                            (int) $item->quantity,
                            ChangeTypeEnum::SALE,
                            $user->id,
                            $invoice->remarks,
                        );
                    }
                }

                // Calculate grand total for receipt
                $grandTotal = $invoice->invoiceItems->reduce(function ($carry, $item) {
                    return $carry + ($item->quantity * $item->rate) - $item->discount_amount + $item->tax_amount;
                }, 0.0);

                // Create receipt if we have an account to credit
                if ($accountId && $grandTotal > 0) {
                    $receiptCount = Receipt::withTrashed()->where('fiscal_year_id', $fiscalYearId)->count();
                    $yearCode = $company->fiscalYear?->year_code;
                    $suffix = $yearCode ? '/' . $yearCode : '';
                    $receiptNo = 'RC-' . ($receiptCount + 1) . $suffix;

                    $receipt = Receipt::create([
                        'fiscal_year_id'   => $fiscalYearId,
                        'party_id'         => $request->party_id ?? null,
                        'receipt_no'       => $receiptNo,
                        'receipt_date'     => $today,
                        'payment_method'   => $request->payment_method,
                        'account_id'       => $accountId,
                        'remarks'          => 'POS Payment - ' . $invoiceNo,
                        'create_user_id'   => $user->id,
                        'approve_user_id'  => $user->id,
                        'approved_at'      => now(),
                        'status'           => StatusEnum::APPROVED->value,
                    ]);

                    $receipt->allocations()->create([
                        'invoice_id' => $invoice->id,
                        'amount'     => round($grandTotal, 2),
                    ]);

                    $invoice->setAttribute('receipt_no', $receipt->receipt_no);
                    $invoice->setAttribute('receipt_id', $receipt->id);
                    $invoice->setAttribute('grand_total', round($grandTotal, 2));
                }

                return $invoice;
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        // Post GL for invoice
        $this->invoiceGl->postFromInvoice($invoice->refresh());

        try {
            SyncInvoiceToIrdJob::dispatch($invoice)->onQueue('ird');
        } catch (\Throwable) {}

        $invoice->load([
            'party',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
        ]);

        $responseData = [
            'id'           => $invoice->id,
            'invoice_no'   => $invoice->invoice_no,
            'receipt_no'   => $invoice->receipt_no ?? null,
            'receipt_id'   => $invoice->receipt_id ?? null,
            'invoice_date' => $invoice->invoice_date,
            'party_id'     => $invoice->party_id,
            'party_name'   => $invoice->party?->name ?? 'Walk-in Customer',
            'grand_total'  => $invoice->grand_total ?? 0,
            'payment_method' => $request->payment_method,
            'items'        => $invoice->invoiceItems->map(fn ($item) => [
                'name'     => $item->productVariant?->product?->name ?? '',
                'quantity' => $item->quantity,
                'rate'     => $item->rate,
                'tax_amount' => $item->tax_amount,
                'discount_amount' => $item->discount_amount,
                'total'    => ($item->quantity * $item->rate) - $item->discount_amount + $item->tax_amount,
            ]),
        ];

        return response()->json([
            'data'    => $responseData,
            'message' => 'Sale completed successfully',
        ], 201);
    }

    /**
     * Save a held order.
     */
    public function holdOrder(Request $request)
    {
        $request->validate([
            'label'                  => ['nullable', 'string', 'max:255'],
            'party_id'               => ['nullable', 'integer'],
            'order_data'             => ['required', 'array'],
            'order_data.items'       => ['required', 'array', 'min:1'],
        ]);

        $held = PosHeldOrder::create([
            'party_id'   => $request->party_id,
            'label'      => $request->label,
            'order_data' => $request->order_data,
        ]);

        $held->load('party:id,name');

        return response()->json([
            'data'    => $this->formatHeldOrder($held),
            'message' => 'Order held successfully',
        ], 201);
    }

    /**
     * List held orders.
     */
    public function heldOrders()
    {
        $orders = PosHeldOrder::with('party:id,name')->latest()->get();

        return response()->json([
            'data' => $orders->map(fn ($o) => $this->formatHeldOrder($o)),
        ]);
    }

    /**
     * Delete a held order.
     */
    public function deleteHeldOrder(PosHeldOrder $posHeldOrder)
    {
        $posHeldOrder->delete();

        return response()->json(['message' => 'Held order deleted']);
    }

    // -----------------------------------------------------------------------

    private function resolveAccountId(string $paymentMethod, ?AccountSetting $setting): ?int
    {
        if (! $setting) {
            return null;
        }

        return match (strtolower($paymentMethod)) {
            'cash'  => $setting->cash_sales_account_id,
            default => $setting->bank_sales_account_id,
        };
    }

    private function formatHeldOrder(PosHeldOrder $order): array
    {
        return [
            'id'         => $order->id,
            'label'      => $order->label,
            'party_id'   => $order->party_id,
            'party_name' => $order->party?->name ?? 'Walk-in Customer',
            'order_data' => $order->order_data,
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }
}
