<?php

use App\Models\Bin;
use App\Models\Bill;
use App\Models\User;
use App\Models\Stock;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\StockLayer;
use App\Enums\UserTypeEnum;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use App\Enums\InventoryCostingMethodEnum;
use App\Http\Resources\Admin\ProductResource;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Services\Inventory\InventoryLayerReceiptService;
use App\Services\Inventory\InventoryLayerTransferService;
use App\Services\Inventory\InventoryDocumentReversalService;
use App\Services\Inventory\InventoryCostingMethodSwitchService;
use App\Services\Inventory\InventoryStockReconciliationAlignService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Test Co',
        'code' => 'TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Tester',
        'email' => 'tester-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main',
        'code' => 'W1',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'SKU-1',
        'is_default' => true,
    ]);

    $this->mainBinId = Bin::defaultIdForWarehouse($this->company->id, $this->warehouse->id);
});

test('fifo receipt creates stock and one layer', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T1',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 10,
        'rate' => 5,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill, $item) {
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    expect($stock)->not->toBeNull();
    expect($stock->quantity)->toBe(10);

    expect(
        StockLayer::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('product_variant_id', $this->variant->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('bin_id', $this->mainBinId)
            ->where('qty_remaining', '>', 0)
            ->count()
    )->toBe(1);
});

test('fifo issue consumes oldest layer first', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T2',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    $issue = app(InventoryLayerIssueService::class);

    DB::transaction(function () use ($receipt, $bill) {
        $itemA = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 5,
            'rate' => 2,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            5,
            InventoryCostCalculator::unitCostFromBillItem($itemA),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemA->id,
        );

        $itemB = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 5,
            'rate' => 4,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            5,
            InventoryCostCalculator::unitCostFromBillItem($itemB),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemB->id,
        );
    });

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-T2',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    DB::transaction(function () use ($issue, $invoice) {
        $movement = $issue->issue(
            $this->company,
            $invoice,
            $this->variant->id,
            $this->warehouse->id,
            7,
            ChangeTypeEnum::SALE,
            $this->user->id,
            null,
        );

        expect($movement->total_cost)->toBe(18.0);
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    expect($stock->quantity)->toBe(3);
});

test('issue without stock throws validation exception', function () {
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-T3',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $issue = app(InventoryLayerIssueService::class);

    expect(fn () => DB::transaction(function () use ($issue, $invoice) {
        $issue->issue(
            $this->company,
            $invoice,
            $this->variant->id,
            $this->warehouse->id,
            1,
            ChangeTypeEnum::SALE,
            $this->user->id,
            null,
        );
    }))->toThrow(ValidationException::class);
});

test('weighted average merges layers on receipt', function () {
    $this->company->update([
        'inventory_costing_method' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE,
    ]);
    $this->company->refresh();

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T4',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill) {
        $itemA = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 10,
            'rate' => 10,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($itemA),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemA->id,
        );

        $itemB = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 10,
            'rate' => 20,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($itemB),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemB->id,
        );
    });

    $layers = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->where('qty_remaining', '>', 0)
        ->get();

    expect($layers)->toHaveCount(1);
    expect($layers->first()->qty_remaining)->toBe(20);
    expect((float) $layers->first()->unit_cost)->toBe(15.0);
});

test('weighted average issue uses merged average for COGS', function () {
    $this->company->update([
        'inventory_costing_method' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE,
    ]);
    $this->company->refresh();

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T-WA-COGS',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    $issue = app(InventoryLayerIssueService::class);

    DB::transaction(function () use ($receipt, $bill, $issue) {
        $itemA = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 10,
            'rate' => 10,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($itemA),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemA->id,
        );

        $itemB = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 10,
            'rate' => 20,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($itemB),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemB->id,
        );

        $invoice = Invoice::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'invoice_no' => 'INV-T-WA-COGS',
            'invoice_date' => now()->toDateString(),
            'create_user_id' => $this->user->id,
            'status' => StatusEnum::DRAFT,
        ]);

        $movement = $issue->issue(
            $this->company,
            $invoice,
            $this->variant->id,
            $this->warehouse->id,
            5,
            ChangeTypeEnum::SALE,
            $this->user->id,
            null,
        );

        expect($movement->total_cost)->toBe(75.0);
    });
});

test('switching company costing from fifo to weighted average consolidates open layers', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T-SWITCH',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill) {
        $itemA = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 5,
            'rate' => 2,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            5,
            InventoryCostCalculator::unitCostFromBillItem($itemA),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemA->id,
        );

        $itemB = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 5,
            'rate' => 4,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            5,
            InventoryCostCalculator::unitCostFromBillItem($itemB),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $itemB->id,
        );
    });

    $switch = app(InventoryCostingMethodSwitchService::class);
    $switch->onCompanyMethodChanging(
        $this->company,
        InventoryCostingMethodEnum::FIFO,
        InventoryCostingMethodEnum::WEIGHTED_AVERAGE,
    );
    $this->company->update([
        'inventory_costing_method' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE,
    ]);

    $layers = StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->where('qty_remaining', '>', 0)
        ->get();

    expect($layers)->toHaveCount(1);
    expect($layers->first()->qty_remaining)->toBe(10);
    expect((float) $layers->first()->unit_cost)->toBe(3.0);
});

test('void approved invoice restores stock and layer quantity', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-T-VOID',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(function () use ($receipt, $bill) {
        $item = BillItem::create([
            'bill_id' => $bill->id,
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'bin_id' => $this->mainBinId,
            'quantity' => 10,
            'rate' => 5,
            'discount_amount' => 0,
        ]);
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'invoice_no' => 'INV-T-VOID',
        'invoice_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    $issue = app(InventoryLayerIssueService::class);
    DB::transaction(function () use ($issue, $invoice) {
        $issue->issue(
            $this->company,
            $invoice,
            $this->variant->id,
            $this->warehouse->id,
            4,
            ChangeTypeEnum::SALE,
            $this->user->id,
            null,
        );
    });

    $reversal = app(InventoryDocumentReversalService::class);
    DB::transaction(function () use ($reversal, $invoice) {
        $reversal->reverseApprovedInvoice($invoice, $this->user->id, null);
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    expect($stock->quantity)->toBe(10);

    $valuedQty = (int) StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->where('qty_remaining', '>', 0)
        ->sum('qty_remaining');

    expect($valuedQty)->toBe(10);
});

test('stock adjustment in and out updates valued quantity', function () {
    $adjustment = StockAdjustment::create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'warehouse_id' => $this->warehouse->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    $issue = app(InventoryLayerIssueService::class);

    DB::transaction(function () use ($adjustment, $receipt, $issue) {
        $receipt->receive(
            $this->company,
            $adjustment,
            $this->variant->id,
            $this->warehouse->id,
            5,
            8.0,
            ChangeTypeEnum::ADJUSTMENT_IN,
            $this->user->id,
            null,
            null,
        );

        $issue->issue(
            $this->company,
            $adjustment,
            $this->variant->id,
            $this->warehouse->id,
            2,
            ChangeTypeEnum::ADJUSTMENT_OUT,
            $this->user->id,
            null,
        );
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    expect($stock->quantity)->toBe(3);
});

test('stock transfer moves quantity between warehouses', function () {
    $warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Secondary',
        'code' => 'W2',
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-TX',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 10,
        'rate' => 3,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);
    DB::transaction(function () use ($receipt, $bill, $item) {
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $transfer = StockTransfer::create([
        'company_id' => $this->company->id,
        'date' => now()->toDateString(),
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $warehouseB->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $binB = Bin::defaultIdForWarehouse($this->company->id, $warehouseB->id);

    $line = StockTransferItem::create([
        'stock_transfer_id' => $transfer->id,
        'product_variant_id' => $this->variant->id,
        'quantity' => 10,
        'from_bin_id' => $this->mainBinId,
        'to_bin_id' => $binB,
    ]);

    $xfer = app(InventoryLayerTransferService::class);
    DB::transaction(function () use ($xfer, $transfer, $line) {
        $xfer->applyLine($this->company, $transfer, $line, $this->user->id, null);
    });

    $qtyFrom = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    $qtyTo = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $warehouseB->id)
        ->where('bin_id', $binB)
        ->first();

    expect($qtyFrom->quantity)->toBe(0);
    expect($qtyTo->quantity)->toBe(10);
});

test('product resource aggregates total_stock and stock_by_warehouse', function () {
    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 7,
        'on_hold' => 0,
    ]);

    $warehouseB = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Branch',
        'code' => 'W2',
    ]);

    $branchBinId = Bin::defaultIdForWarehouse($this->company->id, $warehouseB->id);

    Stock::withoutGlobalScopes()->create([
        'company_id' => $this->company->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $warehouseB->id,
        'bin_id' => $branchBinId,
        'quantity' => 3,
        'on_hold' => 0,
    ]);

    $product = Product::query()
        ->with([
            'variants' => fn ($q) => $q->with(['stocks' => fn ($sq) => $sq->with('warehouse')]),
        ])
        ->findOrFail($this->product->id);

    $data = ProductResource::make($product)->resolve();

    expect($data['total_stock'])->toBe(10);
    expect($data['stock_by_warehouse'])->toHaveCount(2);

    $byId = collect($data['stock_by_warehouse'])->keyBy('warehouse_id');
    expect($byId[$this->warehouse->id]['quantity'])->toBe(7);
    expect($byId[$warehouseB->id]['quantity'])->toBe(3);
    expect($byId[$this->warehouse->id]['warehouse_name'])->toBe('Main');
});

test('purchase receipt posts gl journal when inventory and purchase accounts are set', function () {
    $inventoryAcc = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Inventory GL',
        'code' => 'INV-GL-T',
    ]);
    $purchaseAcc = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Purchases GL',
        'code' => 'PUR-GL-T',
    ]);
    $cogsAcc = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'COGS GL',
        'code' => 'COGS-GL-T',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'inventory_account_id' => $inventoryAcc->id,
        'purchase_account_id' => $purchaseAcc->id,
        'cogs_account_id' => $cogsAcc->id,
    ]);

    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-GL-1',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 4,
        'rate' => 10,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill, $item) {
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            4,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $movement = StockMovement::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->latest('id')
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->gl_journal_id)->not->toBeNull();

    $journal = Journal::withoutGlobalScopes()->find($movement->gl_journal_id);
    expect($journal)->not->toBeNull();
    expect($journal->company_id)->toBe($this->company->id);
    expect($journal->journalItems)->toHaveCount(2);
});

test('reconciliation align valued to on hand adds layers when on hand is overstated', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-ALIGN-1',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 10,
        'rate' => 5,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill, $item) {
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    $stock->quantity = 12;
    $stock->save();

    $align = app(InventoryStockReconciliationAlignService::class);
    $align->alignValuedQuantityToOnHand($this->company, $this->variant->id, $this->warehouse->id, null);

    $stock->refresh();
    $valued = (int) StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->whereNull('deleted_at')
        ->sum('qty_remaining');

    expect((int) $stock->quantity)->toBe(12);
    expect($valued)->toBe(12);
});

test('reconciliation align on hand to valued fixes overstated stock row', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'bill_no' => 'BILL-ALIGN-2',
        'bill_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'bin_id' => $this->mainBinId,
        'quantity' => 10,
        'rate' => 5,
        'discount_amount' => 0,
    ]);

    $receipt = app(InventoryLayerReceiptService::class);

    DB::transaction(function () use ($receipt, $bill, $item) {
        $receipt->receive(
            $this->company,
            $bill,
            $this->variant->id,
            $this->warehouse->id,
            10,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $this->user->id,
            null,
            $item->id,
        );
    });

    $stock = Stock::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->first();

    $stock->quantity = 12;
    $stock->save();

    $align = app(InventoryStockReconciliationAlignService::class);
    $align->alignOnHandQuantityToValued($this->company, $this->variant->id, $this->warehouse->id);

    $stock->refresh();
    $valued = (int) StockLayer::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('product_variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->where('bin_id', $this->mainBinId)
        ->whereNull('deleted_at')
        ->sum('qty_remaining');

    expect((int) $stock->quantity)->toBe(10);
    expect($valued)->toBe(10);
});
