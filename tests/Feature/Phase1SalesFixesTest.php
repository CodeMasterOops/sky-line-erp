<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\ChangeTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Inventory\InventoryCostCalculator;
use App\Services\Inventory\InventoryLayerReceiptService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p1WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

function p1SeedStock(object $test, int $qty): void
{
    p1WarmCache();

    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'bill_no' => 'BILL-P1-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $item = BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => 50,
        'discount_amount' => 0,
    ]);

    DB::transaction(function () use ($test, $bill, $item, $qty) {
        app(InventoryLayerReceiptService::class)->receive(
            $test->company,
            $bill,
            $test->variant->id,
            $test->warehouse->id,
            $qty,
            InventoryCostCalculator::unitCostFromBillItem($item),
            ChangeTypeEnum::PURCHASE,
            $test->user->id,
            null,
            $item->id,
        );
    });
}

function p1AccountSetting(object $test): Account
{
    $account = Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => 'P1 Cash',
        'code' => 'P1CASH-'.uniqid(),
    ]);

    AccountSetting::create([
        'company_id' => $test->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    return $account;
}

beforeEach(function () {
    p1WarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P1 Test Co',
        'code' => 'P1TC',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'P1 Tester',
        'email' => 'p1-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main', 'code' => 'P1W',
    ]);

    $this->product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'P1 Widget', 'code' => 'P1W-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $this->product->id,
        'sku' => 'P1SKU-'.uniqid(),
        'sales_price' => 100,
        'is_default' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
});

// ─── BUG-01: POS receipts now have a GL journal ───────────────────────────────

it('BUG-01: POS cash checkout posts a receipt GL journal', function () {
    p1SeedStock($this, 5);
    p1AccountSetting($this);

    $response = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $response->assertCreated();

    $receipt = Receipt::first();
    expect($receipt)->not->toBeNull();

    $receiptJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', Receipt::class)
        ->where('reference_id', $receipt->id)
        ->where('type', JournalTypeEnum::RECEIPT)
        ->first();

    expect($receiptJournal)->not->toBeNull('Receipt GL journal must be posted on POS checkout');
    expect($receiptJournal->journalItems()->count())->toBe(2);
});

it('BUG-01: POS split payment posts one receipt GL journal per payment', function () {
    p1SeedStock($this, 5);
    p1AccountSetting($this);

    $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'payments' => [
            ['method' => 'cash', 'amount' => 60],
            ['method' => 'card', 'amount' => 40],
        ],
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    expect(Receipt::count())->toBe(2);

    $receiptJournalCount = Journal::withoutGlobalScopes()
        ->where('reference_type', Receipt::class)
        ->where('type', JournalTypeEnum::RECEIPT)
        ->count();

    expect($receiptJournalCount)->toBe(2, 'Each POS receipt must have its own GL journal');
});

it('BUG-01: credit-only POS sale has no receipt and no receipt journal', function () {
    p1SeedStock($this, 5);
    p1AccountSetting($this);

    $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'credit',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    expect(Receipt::count())->toBe(0);
    expect(
        Journal::withoutGlobalScopes()->where('type', JournalTypeEnum::RECEIPT)->count()
    )->toBe(0);
});

// ─── BUG-02: Invoice GL correctly deducts order discount ─────────────────────

it('BUG-02: POS checkout with order discount posts GL DR equal to net grand total', function () {
    p1SeedStock($this, 10);
    p1AccountSetting($this);

    // 2 × 100 = 200 gross; order discount 20 → grand total 180
    $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 20,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    $invoice = Invoice::first();
    $invoiceJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::INVOICE)
        ->with('journalItems')
        ->first();

    expect($invoiceJournal)->not->toBeNull();

    $drTotal = round((float) $invoiceJournal->journalItems->sum('dr_amount'), 2);
    $crTotal = round((float) $invoiceJournal->journalItems->sum('cr_amount'), 2);

    expect($drTotal)->toBe($crTotal, 'Invoice journal must balance');
    expect($drTotal)->toBe(180.0, 'AR debit must equal net grand total (200 − 20 discount = 180)');
});

it('BUG-02: POS checkout without order discount posts GL DR equal to gross amount', function () {
    p1SeedStock($this, 5);
    p1AccountSetting($this);

    $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ])->assertCreated();

    $invoice = Invoice::first();
    $invoiceJournal = Journal::withoutGlobalScopes()
        ->where('reference_type', Invoice::class)
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::INVOICE)
        ->with('journalItems')
        ->first();

    $drTotal = round((float) $invoiceJournal->journalItems->sum('dr_amount'), 2);
    expect($drTotal)->toBe(100.0);
});

// ─── P1-01: Approved receipts cannot be deleted ───────────────────────────────

it('P1-01: approved receipt cannot be deleted via the API', function () {
    p1SeedStock($this, 5);
    p1AccountSetting($this);

    $checkoutRes = $this->postJson('/api/admin/pos/checkout', [
        'payment_method' => 'cash',
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]],
    ]);

    $checkoutRes->assertCreated();

    $receipt = Receipt::first();
    expect($receipt->status->value)->toBe('approved');

    TenantService::setCompanyId($this->company->id);

    $response = $this->deleteJson("/api/admin/receipt/{$receipt->id}");

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('Approved receipts cannot be deleted');
    expect(Receipt::withTrashed()->count())->toBe(1, 'Receipt must not be soft-deleted');
});

it('P1-01: draft receipt can still be deleted', function () {
    $cashAccount = p1AccountSetting($this);
    TenantService::setCompanyId($this->company->id);

    $party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Draft Customer',
        'code' => 'DRCUST-'.uniqid(),
        'type' => PartyTypeEnum::CUSTOMER,
    ]);

    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-DRAFT-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $receipt = Receipt::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'receipt_no' => 'RC-DRAFT-'.uniqid(),
        'receipt_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $cashAccount->id,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $receipt->allocations()->create(['invoice_id' => $invoice->id, 'amount' => 100]);

    $response = $this->deleteJson("/api/admin/receipt/{$receipt->id}");

    $response->assertOk();
    expect(Receipt::withTrashed()->find($receipt->id)?->deleted_at)->not->toBeNull();
});
