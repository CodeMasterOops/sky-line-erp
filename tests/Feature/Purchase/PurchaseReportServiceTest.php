<?php

use App\Models\Bill;
use App\Models\User;
use App\Models\Party;
use App\Models\Account;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\BillItem;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DebitNoteItem;
use App\Models\ProductVariant;
use App\Models\ProductCategory;
use App\Services\TenantService;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function prWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    prWarmCache();
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'PR-Test-FY',
        'year_code' => 'PRFY',
        'start_date' => '2024-07-17',
        'end_date' => '2025-07-16',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'PR Test Co',
        'code' => 'PRTC-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'PR Tester',
        'email' => 'pr-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->party = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Test Supplier',
        'code' => 'SUP-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->category = ProductCategory::create([
        'company_id' => $this->company->id,
        'name' => 'Electronics',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Test Product',
        'code' => 'PROD-PR-'.uniqid(),
        'product_category_id' => $this->category->id,
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-PR-'.uniqid(),
        'sales_price' => 100,
        'purchase_price' => 60,
        'is_default' => true,
    ]);

    $account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'AP Account',
        'code' => 'AP-PR-'.uniqid(),
    ]);
    $this->accountId = $account->id;

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Main Warehouse',
        'code' => 'WH-PR-'.uniqid(),
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function prMakeBill(array $overrides = [], float $qty = 2, float $rate = 100, float $discount = 0, float $tax = 0): Bill
{
    $bill = Bill::create(array_merge([
        'company_id' => test()->company->id,
        'fiscal_year_id' => test()->fiscalYear->id,
        'party_id' => test()->party->id,
        'bill_no' => 'BILL-PR-'.uniqid(),
        'bill_date' => '2024-10-01',
        'status' => StatusEnum::APPROVED,
        'create_user_id' => test()->user->id,
        'approve_user_id' => test()->user->id,
        'approved_at' => now(),
    ], $overrides));

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => test()->variant->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => $discount,
        'tax_amount' => $tax,
    ]);

    return $bill;
}

function prMakeDebitNote(Bill $bill, float $qty = 1, float $rate = 50, string $date = '2024-10-05'): DebitNote
{
    $dn = DebitNote::create([
        'company_id' => test()->company->id,
        'fiscal_year_id' => test()->fiscalYear->id,
        'party_id' => test()->party->id,
        'bill_id' => $bill->id,
        'debit_note_no' => 'DN-PR-'.uniqid(),
        'debit_note_date' => $date,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => test()->user->id,
        'approve_user_id' => test()->user->id,
        'approved_at' => now(),
    ]);

    DebitNoteItem::create([
        'debit_note_id' => $dn->id,
        'product_variant_id' => test()->variant->id,
        'warehouse_id' => test()->warehouse->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    return $dn;
}

function prMakePayment(Bill $bill, float $amount, string $date = '2024-10-10'): Payment
{
    $payment = Payment::create([
        'company_id' => test()->company->id,
        'fiscal_year_id' => test()->fiscalYear->id,
        'party_id' => test()->party->id,
        'account_id' => test()->accountId,
        'payment_no' => 'PAY-PR-'.uniqid(),
        'payment_date' => $date,
        'status' => StatusEnum::APPROVED,
        'create_user_id' => test()->user->id,
        'approve_user_id' => test()->user->id,
        'approved_at' => now(),
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => $amount,
    ]);

    return $payment;
}

// ─── Purchase Summary ─────────────────────────────────────────────────────────

it('purchase summary returns correct KPI totals', function () {
    prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100, tax: 20);
    prMakeBill(['bill_date' => '2024-10-15'], qty: 1, rate: 200, discount: 10);

    $response = $this->getJson('/api/admin/purchase-report/purchase-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $data = $response->json('data');

    expect($data)->toHaveKeys(['period', 'summary', 'party_options']);
    expect($data['summary']['bill_count'])->toBe(2);
    expect($data['summary']['subtotal'])->toEqual(400.0);       // (2*100) + (1*200)
    expect($data['summary']['total_discount'])->toEqual(10.0);
    expect($data['summary']['tax_amount'])->toEqual(20.0);
    expect($data['summary']['net_purchases'])->toEqual(410.0);  // 400 - 10 + 20
});

it('purchase summary excludes voided bills', function () {
    prMakeBill(['bill_date' => '2024-10-01', 'voided_at' => now()]);
    prMakeBill(['bill_date' => '2024-10-01']);

    $response = $this->getJson('/api/admin/purchase-report/purchase-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.bill_count'))->toBe(1);
});

it('purchase summary counts debit note returns', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-01']);
    prMakeDebitNote($bill, qty: 1, rate: 50);

    $response = $this->getJson('/api/admin/purchase-report/purchase-summary?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.summary.return_count'))->toBe(1);
    expect($response->json('data.summary.total_returns'))->toEqual(50.0);
    expect($response->json('data.summary.net_spend'))->toEqual(150.0);  // 200 - 50
});

// ─── Daily Purchase ───────────────────────────────────────────────────────────

it('daily purchase groups bills by day', function () {
    prMakeBill(['bill_date' => '2024-10-01'], rate: 100);
    prMakeBill(['bill_date' => '2024-10-01'], rate: 50);
    prMakeBill(['bill_date' => '2024-10-03'], rate: 200);

    $response = $this->getJson('/api/admin/purchase-report/daily-purchase?from_date=2024-10-01&to_date=2024-10-05');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $day1 = collect($rows)->firstWhere('bill_date', '2024-10-01');
    expect($day1['bill_count'])->toBe(2);
    expect($day1['net_purchases'])->toEqual(300.0);  // (2*100) + (2*50)
});

// ─── Monthly Purchase ─────────────────────────────────────────────────────────

it('monthly purchase groups bills by month with labels', function () {
    prMakeBill(['bill_date' => '2024-10-05'], rate: 100);
    prMakeBill(['bill_date' => '2024-10-20'], rate: 100);
    prMakeBill(['bill_date' => '2024-11-10'], rate: 150);

    $response = $this->getJson('/api/admin/purchase-report/monthly-purchase?from_date=2024-10-01&to_date=2024-11-30');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
    $oct = collect($rows)->firstWhere('month', 10);
    expect($oct['bill_count'])->toBe(2);
    expect($oct['month_label'])->toBe('Oct 2024');
    expect($oct['year'])->toBe(2024);
});

// ─── Yearly Purchase ──────────────────────────────────────────────────────────

it('yearly purchase groups bills by year', function () {
    prMakeBill(['bill_date' => '2024-06-01'], rate: 100);
    prMakeBill(['bill_date' => '2025-01-01'], rate: 200);

    $response = $this->getJson('/api/admin/purchase-report/yearly-purchase?from_date=2024-01-01&to_date=2025-12-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(2);
});

// ─── Supplier Wise Purchase ───────────────────────────────────────────────────

it('supplier wise purchase groups by supplier with outstanding', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100);  // net = 200
    prMakePayment($bill, 80);                                               // paid = 80

    $response = $this->getJson('/api/admin/purchase-report/supplier-wise-purchase?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['bill_count'])->toBe(1);
    expect($rows[0]['net_purchases'])->toEqual(200.0);
    expect($rows[0]['paid'])->toEqual(80.0);
    expect($rows[0]['outstanding'])->toEqual(120.0);
});

// ─── Category Wise Purchase ───────────────────────────────────────────────────

it('category wise purchase groups by product category', function () {
    prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100);
    prMakeBill(['bill_date' => '2024-10-02'], qty: 1, rate: 50);

    $response = $this->getJson('/api/admin/purchase-report/category-wise-purchase?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['category_name'])->toBe('Electronics');
    expect($rows[0]['total_qty'])->toEqual(3.0);  // qty:2 + qty:1
});

// ─── Purchase Return ──────────────────────────────────────────────────────────

it('purchase return lists debit notes with linked bill', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-01']);
    prMakeDebitNote($bill, qty: 2, rate: 60, date: '2024-10-05');

    $response = $this->getJson('/api/admin/purchase-report/purchase-return?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['total_amount'])->toEqual(120.0);
    expect($rows[0]['linked_bill_no'])->toBe($bill->bill_no);
    expect($response->json('data.summary.return_count'))->toBe(1);
});

// ─── Outstanding Purchase ─────────────────────────────────────────────────────

it('outstanding purchase shows bills with remaining balance', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100);  // net = 200
    prMakePayment($bill, 50);                                               // paid = 50, balance = 150

    $response = $this->getJson('/api/admin/purchase-report/outstanding-purchase');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['balance_due'])->toEqual(150.0);
    expect($response->json('data.summary.balance_due'))->toEqual(150.0);
});

it('outstanding purchase excludes fully paid bills', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-01'], qty: 1, rate: 100);
    prMakePayment($bill, 200.0);  // overpaid

    $response = $this->getJson('/api/admin/purchase-report/outstanding-purchase');

    $response->assertOk();
    expect($response->json('data.rows'))->toHaveCount(0);
});

// ─── Purchase Tax ─────────────────────────────────────────────────────────────

it('purchase tax groups bill items by tax type', function () {
    prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100, tax: 26);

    $response = $this->getJson('/api/admin/purchase-report/purchase-tax?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $data = $response->json('data');

    expect($data['rows'])->toHaveCount(1);
    expect($data['rows'][0]['tax_name'])->toBe('No Tax');
    expect($data['summary']['tax_amount'])->toEqual(26.0);
    expect($data['summary']['taxable_amount'])->toEqual(200.0);
});

// ─── Purchase Ledger ──────────────────────────────────────────────────────────

it('purchase ledger returns empty when no party selected', function () {
    $response = $this->getJson('/api/admin/purchase-report/purchase-ledger?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.party'))->toBeNull();
    expect($response->json('data.rows'))->toBeEmpty();
});

it('purchase ledger shows bills and payments with running balance', function () {
    $bill = prMakeBill(['bill_date' => '2024-10-05'], qty: 2, rate: 100);  // credit 200
    prMakePayment($bill, 80, '2024-10-10');                                 // debit 80

    $response = $this->getJson('/api/admin/purchase-report/purchase-ledger?from_date=2024-10-01&to_date=2024-10-31&party_id='.$this->party->id);

    $response->assertOk();
    $data = $response->json('data');

    expect($data['party']['id'])->toBe($this->party->id);
    expect($data['rows'])->toHaveCount(2);

    $billTx = collect($data['rows'])->firstWhere('type', 'Bill');
    expect($billTx['credit'])->toEqual(200.0);

    $paymentTx = collect($data['rows'])->firstWhere('type', 'Payment');
    expect($paymentTx['debit'])->toEqual(80.0);

    expect($data['closing_balance'])->toEqual(120.0);  // 200 - 80
});

it('purchase ledger includes opening balance from prior period', function () {
    // Bill before period: qty=1, rate=100 → total = 100
    prMakeBill(['bill_date' => '2024-09-01'], qty: 1, rate: 100);

    $response = $this->getJson('/api/admin/purchase-report/purchase-ledger?from_date=2024-10-01&to_date=2024-10-31&party_id='.$this->party->id);

    $response->assertOk();
    expect($response->json('data.opening_balance'))->toEqual(100.0);
});

// ─── Purchase Discount ────────────────────────────────────────────────────────

it('purchase discount shows bills with discounts applied', function () {
    prMakeBill(['bill_date' => '2024-10-01'], qty: 2, rate: 100, discount: 15);
    prMakeBill(['bill_date' => '2024-10-02'], qty: 1, rate: 50);  // no discount — excluded

    $response = $this->getJson('/api/admin/purchase-report/purchase-discount?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    $rows = $response->json('data.rows');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['line_discount'])->toEqual(15.0);
    expect($rows[0]['total_discount'])->toEqual(15.0);
    expect($response->json('data.summary.total_discount'))->toEqual(15.0);
});

// ─── Pending Purchase ─────────────────────────────────────────────────────────

it('pending purchase returns empty when no purchase orders exist', function () {
    $response = $this->getJson('/api/admin/purchase-report/pending-purchase?from_date=2024-10-01&to_date=2024-10-31');

    $response->assertOk();
    expect($response->json('data.rows'))->toBeEmpty();
    expect($response->json('data.summary.total_orders'))->toBe(0);
});
