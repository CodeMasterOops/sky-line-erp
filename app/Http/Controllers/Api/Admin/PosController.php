<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Party;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\CreditNote;
use App\Models\PosSession;
use App\Enums\PartyTypeEnum;
use App\Models\PosHeldOrder;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\PosCashMovement;
use App\Models\ProductCategory;
use App\Jobs\SyncInvoiceToIrdJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\DocumentNumberGenerator;
use App\Services\Nepal\NepaliDateService;
use App\Services\Accounting\GlAccountConfigGuard;
use App\Http\Requests\Api\Admin\PosCheckoutRequest;
use App\Services\Accounting\InvoiceGlPostingService;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Accounting\CreditNoteGlPostingService;
use App\Services\Inventory\SalesReturnUnitCostResolver;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Http\Resources\Admin\Inventory\ProductCategoryResource;

class PosController extends Controller
{
    public function __construct(
        private InventoryLayerIssueService $inventoryIssue,
        private InventoryLayerReceiptService $inventoryReceipt,
        private InvoiceGlPostingService $invoiceGl,
        private CreditNoteGlPostingService $creditNoteGl,
        private SalesReturnUnitCostResolver $salesReturnCostResolver,
        private NepaliDateService $nepaliDate,
        private DocumentNumberGenerator $documentNumberGenerator,
        private GlAccountConfigGuard $glAccountGuard,
    ) {}

    /**
     * Category tree for POS filtering.
     */
    public function categories()
    {
        $categories = ProductCategory::query()
            ->with('parent:id,name')
            ->withCount('children')
            ->orderByRaw('parent_id is null desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ProductCategoryResource::collection($categories),
        ]);
    }

    /**
     * Products list optimised for POS – all variants with stock per warehouse.
     */
    public function products(Request $request)
    {
        $warehouseId = $request->integer('warehouse_id');
        $categoryId = $request->integer('category_id');
        $limit = $request->integer('limit');
        $search = trim((string) $request->get('search', ''));

        $query = Product::query()
            ->physical()
            ->with([
                'productCategory.parent:id,name',
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
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('code', 'like', $like);
            });
        }

        $products = $limit > 0 ? $query->latest('id')->limit($limit)->get() : $query->latest('id')->get();

        $data = $products->flatMap(function ($product) {
            return $product->variants->map(function ($variant) use ($product) {
                $stock = $variant->stocks->sum('quantity');

                return [
                    'id' => $variant->id,
                    'product_id' => $product->id,
                    'name' => $product->name.($variant->variant_label ? ' - '.$variant->variant_label : ''),
                    'sku' => $variant->sku,
                    'sales_price' => $variant->sales_price ?? 0,
                    'purchase_price' => $variant->purchase_price ?? 0,
                    'image' => $product->image,
                    'category_id' => $product->product_category_id,
                    'category_name' => $product->productCategory?->fullPath() ?? '',
                    'unit_id' => $product->unit_id,
                    'stock' => (float) $stock,
                    'is_default' => $variant->is_default,
                ];
            });
        });

        return response()->json(['data' => $data->values()]);
    }

    /**
     * Warehouses with available stock for a product variant (POS add-to-cart).
     */
    public function variantWarehouses(ProductVariant $productVariant)
    {
        $companyId = auth('admin')->user()->company_id;

        $stocks = DB::table('stocks')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->where('stocks.company_id', $companyId)
            ->where('stocks.product_variant_id', $productVariant->id)
            ->where('stocks.quantity', '>', 0)
            ->whereNull('stocks.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->select([
                'stocks.warehouse_id',
                'warehouses.name as warehouse_name',
                'stocks.quantity',
            ])
            ->orderBy('warehouses.name')
            ->get();

        return response()->json([
            'data' => $stocks->map(fn ($row) => [
                'warehouse_id' => $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'quantity' => (float) $row->quantity,
            ])->values(),
        ]);
    }

    /**
     * Customers (party type = customer) for POS customer selector.
     */
    public function customers(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $query = Party::where('type', PartyTypeEnum::CUSTOMER)->where('is_active', true);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            });
        }

        $customers = $query->with('discount')->limit(50)->get();

        return response()->json([
            'data' => $customers->map(fn (Party $party) => [
                'id' => $party->id,
                'name' => $party->name,
                'phone' => $party->phone,
                'email' => $party->email,
                'pan' => $party->pan ?? '',
                'discount_type' => $party->discount?->type ?? '',
                'discount_value' => $party->discount?->value ?? '',
            ]),
        ]);
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
                'sale_count' => (int) ($totals->sale_count ?? 0),
                'sale_total' => round($saleTotal, 2),
                'cogs' => round($cogs, 2),
                'profit' => round($profit, 2),
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
    public function checkout(PosCheckoutRequest $request)
    {
        $user = auth('admin')->user();
        $company = $user->company;
        $fiscalYearId = $company->fiscal_year_id;
        $today = now()->toDateString();

        $accountSetting = AccountSetting::first();
        $accountId = $this->resolveAccountId($request->payment_method, $accountSetting);

        $yearCode = $company->fiscalYear?->year_code;

        try {
            $hasTax = collect($request->items)->contains(
                fn ($item) => (float) ($item['tax_amount'] ?? 0) > 0
            );
            $this->glAccountGuard->assertSalesPostable($hasTax);

            $invoice = DB::transaction(function () use ($request, $user, $company, $fiscalYearId, $today, $yearCode, $accountSetting) {
                // See InvoiceService for the lock-inside-transaction concurrency note.
                $invoiceNo = $this->documentNumberGenerator->fiscalYear(
                    Invoice::class,
                    'INV-',
                    $fiscalYearId,
                    $yearCode,
                );
                $invoiceDateBs = null;
                try {
                    $bs = $this->nepaliDate->adToBs($today);
                    $invoiceDateBs = $this->nepaliDate->formatBs($bs['year'], $bs['month'], $bs['day']);
                } catch (\Throwable) {
                }

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'fiscal_year_id' => $fiscalYearId,
                    'party_id' => $request->party_id ?? null,
                    'invoice_no' => $invoiceNo,
                    'invoice_date' => $today,
                    'invoice_date_bs' => $invoiceDateBs,
                    'due_date' => $today,
                    'remarks' => $request->remarks ?? 'POS Sale',
                    'create_user_id' => $user->id,
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $lineDiscountTotal = 0.0;
                $lineNetTotal = 0.0;

                foreach ($request->items as $item) {
                    $lineSubtotal = (float) $item['quantity'] * (float) $item['rate'];
                    $lineDiscount = (float) ($item['discount_amount'] ?? 0);
                    $lineDiscountTotal += $lineDiscount;
                    $lineNetTotal += max($lineSubtotal - $lineDiscount, 0);
                }

                $orderDiscountAmount = $this->resolveOrderDiscountAmount(
                    $lineNetTotal,
                    $request->input('order_discount_type', 'fixed'),
                    (float) ($request->input('order_discount_value') ?? 0),
                );

                if ($request->filled('order_discount_type') || $request->filled('order_discount_value')) {
                    $invoice->saveDiscount(
                        $request->input('order_discount_type', 'fixed'),
                        $request->filled('order_discount_value') ? (float) $request->order_discount_value : null,
                        $orderDiscountAmount,
                    );
                }

                $invoiceItems = collect($request->items)->map(fn ($item) => [
                    'company_id' => $company->id,
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_line_type' => 'taxable',
                ])->all();

                $createdItems = $invoice->invoiceItems()->createMany($invoiceItems);
                $invoice->refresh()->loadMissing(['invoiceItems', 'discount']);

                foreach ($createdItems as $index => $invoiceItem) {
                    $sourceItem = $request->items[$index] ?? null;
                    if ($sourceItem && (isset($sourceItem['line_discount_type']) || isset($sourceItem['line_discount_value']))) {
                        $invoiceItem->saveDiscount(
                            $sourceItem['line_discount_type'] ?? 'fixed',
                            isset($sourceItem['line_discount_value']) ? (float) $sourceItem['line_discount_value'] : null,
                            (float) ($sourceItem['discount_amount'] ?? 0),
                        );
                    }
                }

                // Deduct inventory
                $invoice->loadMissing('invoiceItems.productVariant.product');
                foreach ($invoice->invoiceItems as $item) {
                    if ((int) $item->quantity <= 0) {
                        continue;
                    }

                    if ($item->productVariant?->isService()) {
                        continue;
                    }

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

                $subtotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->quantity * (float) $item->rate);
                $lineDiscountTotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->discount_amount);
                $taxTotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->tax_amount);
                $orderDiscountAmount = (float) ($invoice->discount?->amount ?? $orderDiscountAmount);
                $grandTotal = $subtotal - $lineDiscountTotal - $orderDiscountAmount + $taxTotal;

                // Build payment entries: split payments override single payment_method
                $payments = $request->payments ?? null;
                if (! $payments && strtolower($request->payment_method) !== 'credit') {
                    $payments = [['method' => $request->payment_method, 'amount' => $grandTotal]];
                }

                $firstReceipt = null;
                if ($payments && $grandTotal > 0) {
                    $remaining = round($grandTotal, 2);
                    foreach ($payments as $payment) {
                        $method = strtolower($payment['method']);
                        $payAccountId = $this->resolveAccountId($method, $accountSetting);
                        if (! $payAccountId) {
                            continue;
                        }
                        $payAmount = min(round((float) $payment['amount'], 2), $remaining);
                        if ($payAmount <= 0) {
                            continue;
                        }

                        $receiptNo = $this->documentNumberGenerator->fiscalYear(
                            Receipt::class,
                            'RC-',
                            $fiscalYearId,
                            $yearCode,
                        );

                        $receipt = Receipt::create([
                            'company_id' => $company->id,
                            'fiscal_year_id' => $fiscalYearId,
                            'party_id' => $request->party_id ?? null,
                            'receipt_no' => $receiptNo,
                            'receipt_date' => $today,
                            'payment_method' => $method,
                            'account_id' => $payAccountId,
                            'remarks' => 'POS Payment - '.$invoiceNo,
                            'create_user_id' => $user->id,
                            'approve_user_id' => $user->id,
                            'approved_at' => now(),
                            'status' => StatusEnum::APPROVED->value,
                        ]);

                        $receipt->allocations()->create([
                            'invoice_id' => $invoice->id,
                            'amount' => $payAmount,
                        ]);

                        $firstReceipt ??= $receipt;
                        $remaining -= $payAmount;
                    }
                }

                $invoice->setAttribute('receipt_no', $firstReceipt?->receipt_no);
                $invoice->setAttribute('receipt_id', $firstReceipt?->id);
                $invoice->setAttribute('grand_total', round($grandTotal, 2));

                return $invoice;
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        // Post GL for invoice
        $this->invoiceGl->postFromInvoice($invoice->refresh());

        try {
            SyncInvoiceToIrdJob::dispatch($invoice)->onQueue('ird');
        } catch (\Throwable) {
        }

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'receiptAllocations.receipt',
        ]);

        $receipts = $invoice->receiptAllocations->map(fn ($a) => $a->receipt)->filter();
        $displayMethod = match (true) {
            $receipts->isEmpty() => 'credit',
            $receipts->pluck('payment_method')->unique()->count() > 1 => 'split',
            default => $receipts->first()?->payment_method ?? $request->payment_method,
        };
        $responseData = $this->buildReceiptData($invoice, $displayMethod);
        $firstReceipt = $receipts->first();
        $responseData['receipt_no'] = $firstReceipt?->receipt_no ?? null;
        $responseData['receipt_id'] = $firstReceipt?->id ?? null;
        $responseData['payments'] = $receipts->map(fn ($r) => [
            'method' => $r->payment_method,
            'amount' => (float) ($invoice->receiptAllocations->firstWhere('receipt_id', $r->id)?->amount ?? 0),
            'receipt_no' => $r->receipt_no,
        ])->values()->all();

        return response()->json([
            'data' => $responseData,
            'message' => 'Sale completed successfully',
        ], 201);
    }

    /**
     * Save a held order.
     */
    public function holdOrder(Request $request)
    {
        $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'party_id' => ['nullable', 'integer'],
            'order_data' => ['required', 'array'],
            'order_data.items' => ['required', 'array', 'min:1'],
        ]);

        $held = PosHeldOrder::create([
            'company_id' => auth('admin')->user()->company_id,
            'party_id' => $request->party_id,
            'label' => $request->label,
            'order_data' => $request->order_data,
        ]);

        $held->load('party:id,name');

        return response()->json([
            'data' => $this->formatHeldOrder($held),
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

    // ─── Till / Cash Register ──────────────────────────────────────────────

    /**
     * Return the currently open session for this user, or null.
     */
    public function currentSession(): JsonResponse
    {
        $session = $this->getOpenSession();

        return response()->json([
            'data' => $session ? $this->formatSession($session) : null,
        ]);
    }

    /**
     * Open a new till session.
     */
    public function openTill(Request $request): JsonResponse
    {
        $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $user = auth('admin')->user();

        $existing = $this->getOpenSession();
        if ($existing) {
            return response()->json([
                'data' => $this->formatSession($existing),
                'message' => 'A session is already open.',
            ]);
        }

        $session = PosSession::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_cash' => (float) $request->opening_cash,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json([
            'data' => $this->formatSession($session),
            'message' => 'Till opened successfully.',
        ], 201);
    }

    /**
     * Close the current till session with a Z-Report snapshot.
     */
    public function closeTill(Request $request): JsonResponse
    {
        $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->getOpenSession();
        if (! $session) {
            return response()->json(['message' => 'No open till session found.'], 422);
        }

        $summary = $this->buildTillSummaryData($session);
        $expectedCash = $summary['expected_cash'];
        $closingCash = (float) $request->closing_cash;

        $session->update([
            'closing_cash' => $closingCash,
            'expected_cash' => $expectedCash,
            'cash_difference' => round($closingCash - $expectedCash, 2),
            'notes' => $request->notes,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'data' => array_merge($this->formatSession($session->fresh()), ['summary' => $summary]),
            'message' => 'Till closed successfully.',
        ]);
    }

    /**
     * Record a cash-in or cash-out movement against the open session.
     */
    public function cashMovement(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'in:cash_in,cash_out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->getOpenSession();
        if (! $session) {
            return response()->json(['message' => 'No open till session. Open the till first.'], 422);
        }

        $user = auth('admin')->user();

        $movement = PosCashMovement::create([
            'pos_session_id' => $session->id,
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'type' => $request->type,
            'amount' => (float) $request->amount,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'data' => $this->formatMovement($movement),
            'message' => ucfirst(str_replace('_', ' ', $request->type)).' recorded.',
        ], 201);
    }

    /**
     * Return live Z-Report data for the current open session.
     */
    public function tillSummary(): JsonResponse
    {
        $session = $this->getOpenSession();
        if (! $session) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => array_merge(
                $this->formatSession($session),
                $this->buildTillSummaryData($session),
            ),
        ]);
    }

    // ─── Recent Transactions ──────────────────────────────────────────────────

    /**
     * Paginated invoice list for the POS "recent transactions" panel.
     * Adds payment_method (from first receipt) and has_returns flag.
     */
    public function transactions(Request $request): JsonResponse
    {
        $companyId = auth('admin')->user()->company_id;
        $today = now()->toDateString();

        $dateFrom = $request->get('date_from', $today);
        $dateTo = $request->get('date_to', $today);
        $search = trim((string) $request->get('search', ''));
        $limit = min((int) $request->get('limit', 50), 200);
        $page = max((int) $request->get('page', 1), 1);
        $offset = ($page - 1) * $limit;

        $grandTotalSub = DB::table('invoice_items as ii')
            ->selectRaw('ii.invoice_id, ROUND(SUM(ii.quantity * ii.rate) - SUM(ii.discount_amount) + SUM(ii.tax_amount), 2) as grand_total')
            ->whereNull('ii.deleted_at')
            ->groupBy('ii.invoice_id');

        $paymentSub = DB::table('receipts as r')
            ->join('receipt_allocations as ra', 'ra.receipt_id', '=', 'r.id')
            ->selectRaw('ra.invoice_id, r.payment_method')
            ->where('r.status', StatusEnum::APPROVED->value)
            ->whereNull('r.deleted_at')
            ->whereNull('ra.deleted_at');

        $query = DB::table('invoices as inv')
            ->leftJoinSub($grandTotalSub, 'totals', 'totals.invoice_id', '=', 'inv.id')
            ->leftJoinSub($paymentSub, 'pay', 'pay.invoice_id', '=', 'inv.id')
            ->leftJoin('parties as p', 'p.id', '=', 'inv.party_id')
            ->selectRaw('
                inv.id,
                inv.invoice_no,
                inv.invoice_date,
                inv.invoice_date_bs,
                inv.party_id,
                p.name as party_name,
                COALESCE(totals.grand_total, 0) as grand_total,
                pay.payment_method,
                EXISTS(
                    SELECT 1 FROM credit_notes cn
                    WHERE cn.invoice_id = inv.id
                      AND cn.deleted_at IS NULL
                ) as has_returns
            ')
            ->where('inv.company_id', $companyId)
            ->where('inv.status', StatusEnum::APPROVED->value)
            ->whereNull('inv.deleted_at')
            ->whereBetween('inv.invoice_date', [$dateFrom, $dateTo]);

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('inv.invoice_no', 'like', $like)
                    ->orWhere('p.name', 'like', $like)
                    ->orWhereRaw('CAST(COALESCE(totals.grand_total, 0) AS CHAR) LIKE ?', [$like]);
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('inv.invoice_date')
            ->orderByDesc('inv.id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'invoice_no' => $row->invoice_no,
                'invoice_date' => $row->invoice_date,
                'invoice_date_bs' => $row->invoice_date_bs,
                'party_name' => $row->party_name ?? 'Walk-in Customer',
                'grand_total' => (float) $row->grand_total,
                'payment_method' => $row->payment_method ?? 'credit',
                'has_returns' => (bool) $row->has_returns,
                'status' => 'approved',
            ]),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / max($limit, 1)),
            ],
        ]);
    }

    // ─── Return / Refund ──────────────────────────────────────────────────────

    /**
     * Process a POS return: creates an auto-approved credit note, restores inventory, posts GL.
     */
    public function processReturn(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = auth('admin')->user();
        $invoice = Invoice::findOrFail($request->invoice_id);

        abort_unless($invoice->company_id === $user->company_id, 403);
        abort_unless($invoice->status === StatusEnum::APPROVED, 422, 'Can only return items from approved invoices.');

        $company = $user->company;
        $fiscalYearId = $company->fiscal_year_id;

        try {
            $creditNote = DB::transaction(function () use ($request, $invoice, $user, $company, $fiscalYearId) {
                $creditNoteNo = $this->documentNumberGenerator->fiscalYear(
                    CreditNote::class,
                    'CN-',
                    $fiscalYearId,
                    $company->fiscalYear?->year_code,
                );

                $creditNote = CreditNote::create([
                    'company_id' => $user->company_id,
                    'fiscal_year_id' => $fiscalYearId,
                    'party_id' => $invoice->party_id,
                    'invoice_id' => $invoice->id,
                    'credit_note_no' => $creditNoteNo,
                    'credit_note_date' => now()->toDateString(),
                    'remarks' => $request->reason,
                    'create_user_id' => $user->id,
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED,
                ]);

                foreach ($request->items as $item) {
                    $creditNote->creditNoteItems()->create([
                        'invoice_item_id' => $item['invoice_item_id'] ?? null,
                        'product_variant_id' => $item['product_variant_id'],
                        'warehouse_id' => $item['warehouse_id'],
                        'quantity' => (int) $item['quantity'],
                        'rate' => (float) $item['rate'],
                        'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                        'discount_amount' => (float) ($item['discount_amount'] ?? 0),
                    ]);
                }

                $creditNote->load('creditNoteItems.productVariant.product');

                foreach ($creditNote->creditNoteItems as $creditItem) {
                    $qty = (int) $creditItem->quantity;
                    if ($qty <= 0 || $creditItem->productVariant?->isService()) {
                        continue;
                    }

                    $unitCost = $this->salesReturnCostResolver->resolve(
                        $company,
                        $creditNote->invoice_id,
                        $creditItem->product_variant_id,
                        $creditItem->warehouse_id,
                        $creditItem->invoice_item_id,
                    );

                    $this->inventoryReceipt->receive(
                        $company,
                        $creditNote,
                        $creditItem->product_variant_id,
                        $creditItem->warehouse_id,
                        $qty,
                        $unitCost,
                        ChangeTypeEnum::RETURN_IN,
                        $user->id,
                        $creditNote->remarks,
                        null,
                    );
                }

                $this->creditNoteGl->postFromCreditNote($creditNote);

                return $creditNote;
            });
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $creditNote->load([
            'party',
            'invoice',
            'creditNoteItems.productVariant.product',
            'creditNoteItems.warehouse',
        ]);

        $refundTotal = $creditNote->creditNoteItems->sum(
            fn ($i) => round(((float) $i->quantity * (float) $i->rate) - (float) $i->discount_amount + (float) $i->tax_amount, 2)
        );

        return response()->json([
            'data' => [
                'id' => $creditNote->id,
                'credit_note_no' => $creditNote->credit_note_no,
                'credit_note_date' => $creditNote->credit_note_date,
                'invoice_no' => $creditNote->invoice?->invoice_no,
                'party_name' => $creditNote->party?->name ?? 'Walk-in Customer',
                'refund_total' => round($refundTotal, 2),
                'reason' => $creditNote->remarks,
                'items' => $creditNote->creditNoteItems->map(fn ($i) => [
                    'name' => $i->productVariant?->product?->name ?? '',
                    'sku' => $i->productVariant?->sku ?? '',
                    'warehouse_name' => $i->warehouse?->name ?? '',
                    'quantity' => $i->quantity,
                    'rate' => (float) $i->rate,
                    'tax_amount' => (float) $i->tax_amount,
                    'discount_amount' => (float) $i->discount_amount,
                    'total' => round(((float) $i->quantity * (float) $i->rate) - (float) $i->discount_amount + (float) $i->tax_amount, 2),
                ])->values(),
            ],
            'message' => 'Return processed successfully.',
        ], 201);
    }

    // ─── Receipt Data (reprint) ────────────────────────────────────────────

    /**
     * Return full receipt data for an existing invoice (used for reprinting).
     */
    public function receiptData(Invoice $invoice): JsonResponse
    {
        abort_unless(
            $invoice->company_id === auth('admin')->user()->company_id,
            403,
        );

        $invoice->load([
            'party',
            'discount',
            'invoiceItems.productVariant.product',
            'invoiceItems.unit',
            'invoiceItems.tax',
            'invoiceItems.warehouse',
            'receiptAllocations.receipt',
        ]);

        $receipts = $invoice->receiptAllocations->map(fn ($a) => $a->receipt)->filter();
        $displayMethod = match (true) {
            $receipts->isEmpty() => 'credit',
            $receipts->pluck('payment_method')->unique()->count() > 1 => 'split',
            default => $receipts->first()?->payment_method ?? 'cash',
        };
        $firstReceipt = $receipts->first();
        $data = $this->buildReceiptData($invoice, $displayMethod);
        $data['receipt_no'] = $firstReceipt?->receipt_no ?? null;
        $data['receipt_id'] = $firstReceipt?->id ?? null;
        $data['payments'] = $receipts->map(fn ($r) => [
            'method' => $r->payment_method,
            'amount' => (float) ($invoice->receiptAllocations->firstWhere('receipt_id', $r->id)?->amount ?? 0),
            'receipt_no' => $r->receipt_no,
        ])->values()->all();

        return response()->json(['data' => $data]);
    }

    // -----------------------------------------------------------------------

    private function resolveOrderDiscountAmount(float $sumLineNet, string $type, float $value): float
    {
        if ($sumLineNet <= 0 || $value <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            $pct = min(100.0, max(0.0, $value));

            return min($sumLineNet * ($pct / 100), $sumLineNet);
        }

        return min(max(0.0, $value), $sumLineNet);
    }

    private function resolveAccountId(string $paymentMethod, ?AccountSetting $setting): ?int
    {
        if (! $setting) {
            return null;
        }

        return match (strtolower($paymentMethod)) {
            'cash' => $setting->cash_sales_account_id,
            'credit' => null, // No receipt — invoice left as open receivable
            default => $setting->bank_sales_account_id,
        };
    }

    private function getOpenSession(): ?PosSession
    {
        $companyId = auth('admin')->user()->company_id;

        return PosSession::where('company_id', $companyId)
            ->where('status', 'open')
            ->latest()
            ->first();
    }

    private function formatSession(PosSession $session): array
    {
        return [
            'id' => $session->id,
            'status' => $session->status,
            'opening_cash' => (float) $session->opening_cash,
            'closing_cash' => $session->closing_cash !== null ? (float) $session->closing_cash : null,
            'expected_cash' => $session->expected_cash !== null ? (float) $session->expected_cash : null,
            'cash_difference' => $session->cash_difference !== null ? (float) $session->cash_difference : null,
            'notes' => $session->notes,
            'opened_at' => $session->opened_at?->toDateTimeString(),
            'closed_at' => $session->closed_at?->toDateTimeString(),
            'user_name' => $session->user?->name ?? '',
        ];
    }

    private function formatMovement(PosCashMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'type' => $movement->type,
            'amount' => (float) $movement->amount,
            'reason' => $movement->reason,
            'created_at' => $movement->created_at?->toDateTimeString(),
        ];
    }

    /** @return array{cash_sales: float, sales_by_method: array<string,float>, cash_ins: float, cash_outs: float, expected_cash: float, movements: list<array<string,mixed>>} */
    private function buildTillSummaryData(PosSession $session): array
    {
        $companyId = $session->company_id;

        // Sales grouped by payment method since session opened
        $salesByMethod = DB::table('receipts as r')
            ->join('receipt_allocations as ra', 'ra.receipt_id', '=', 'r.id')
            ->where('r.company_id', $companyId)
            ->where('r.status', StatusEnum::APPROVED->value)
            ->where('r.created_at', '>=', $session->opened_at)
            ->whereNull('r.deleted_at')
            ->whereNull('ra.deleted_at')
            ->groupBy('r.payment_method')
            ->selectRaw('r.payment_method, ROUND(SUM(ra.amount), 2) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->payment_method => (float) $row->total])
            ->all();

        $cashSales = $salesByMethod['cash'] ?? 0.0;

        // Cash movements
        $movements = $session->cashMovements()->latest()->get();
        $cashIns = $movements->where('type', 'cash_in')->sum('amount');
        $cashOuts = $movements->where('type', 'cash_out')->sum('amount');
        $expectedCash = round((float) $session->opening_cash + $cashSales + $cashIns - $cashOuts, 2);

        return [
            'cash_sales' => round($cashSales, 2),
            'sales_by_method' => $salesByMethod,
            'cash_ins' => round((float) $cashIns, 2),
            'cash_outs' => round((float) $cashOuts, 2),
            'expected_cash' => $expectedCash,
            'movements' => $movements->map(fn ($m) => $this->formatMovement($m))->values()->all(),
        ];
    }

    private function buildReceiptData(Invoice $invoice, string $paymentMethod): array
    {
        $warehouseNames = $invoice->invoiceItems
            ->pluck('warehouse.name')
            ->filter()
            ->unique()
            ->values();

        $subtotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->quantity * (float) $item->rate);
        $lineDiscountTotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->discount_amount);
        $taxTotal = $invoice->invoiceItems->sum(fn ($item) => (float) $item->tax_amount);
        $orderDiscountAmount = (float) ($invoice->discount?->amount ?? 0);
        $taxableAmount = $subtotal - $lineDiscountTotal - $orderDiscountAmount;
        $grandTotal = $taxableAmount + $taxTotal;

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => $invoice->invoice_date,
            'invoice_date_bs' => $invoice->invoice_date_bs,
            'party_id' => $invoice->party_id,
            'party_name' => $invoice->party?->name ?? 'Walk-in Customer',
            'party_pan' => $invoice->party?->pan ?? null,
            'warehouse_name' => $warehouseNames->count() === 1
                ? ($warehouseNames->first() ?? '')
                : ($warehouseNames->count() > 1 ? 'Multiple warehouses' : ''),
            'warehouses' => $warehouseNames->all(),
            'subtotal' => round($subtotal, 2),
            'line_discount_total' => round($lineDiscountTotal, 2),
            'order_discount_amount' => round($orderDiscountAmount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($grandTotal, 2),
            'payment_method' => $paymentMethod,
            'items' => $invoice->invoiceItems->map(fn ($item) => [
                'id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'name' => $item->productVariant?->product?->name ?? '',
                'sku' => $item->productVariant?->sku ?? '',
                'warehouse_name' => $item->warehouse?->name ?? '',
                'quantity' => $item->quantity,
                'rate' => $item->rate,
                'tax_name' => $item->tax?->name ?? '',
                'tax_rate' => (float) ($item->tax?->rate ?? 0),
                'tax_amount' => $item->tax_amount,
                'discount_amount' => $item->discount_amount,
                'total' => ($item->quantity * $item->rate) - $item->discount_amount + $item->tax_amount,
            ])->values()->all(),
        ];
    }

    private function formatHeldOrder(PosHeldOrder $order): array
    {
        return [
            'id' => $order->id,
            'label' => $order->label,
            'party_id' => $order->party_id,
            'party_name' => $order->party?->name ?? 'Walk-in Customer',
            'order_data' => $order->order_data,
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }
}
