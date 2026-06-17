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
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Models\PaymentMode;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\AccountSetting;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function ppWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function ppMakeApprovedBill(object $test, float $rate = 100, int $qty = 1, ?string $dueDate = null): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'bill_no' => 'BILL-PP-'.uniqid(),
        'bill_date' => '2026-06-15',
        'due_date' => $dueDate,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $test->approver->id,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    return $bill;
}

function ppAllocatePayment(object $test, Bill $bill, float $amount): Payment
{
    $payment = Payment::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'payment_no' => 'PP-ALLOC-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $test->paymentMode->id,
        'account_id' => $test->account->id,
        'gross_amount' => $amount,
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => $amount,
    ]);

    return $payment;
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    ppWarmCache();
    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'PP Test Co',
        'code' => 'PPTCO',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'PP Creator',
        'email' => 'pp-creator-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->approver = User::create([
        'company_id' => $this->company->id,
        'name' => 'PP Approver',
        'email' => 'pp-approver-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'PP Supplier',
        'code' => 'PP-SUP-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'PP Warehouse',
        'code' => 'PP-W',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'PP Widget',
        'code' => 'PP-PROD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'PP-SKU-'.uniqid(),
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'PP Cash',
        'code' => 'PP-CASH-'.uniqid(),
    ]);

    $this->paymentMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash',
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── PERF-001: dashboard uses SQL aggregates ─────────────────────────────────

it('PERF-001: dashboard returns correct total_bills count', function () {
    ppMakeApprovedBill($this, 100);
    ppMakeApprovedBill($this, 200);

    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.total_bills'))->toBe(2);
});

it('PERF-001: dashboard total_amount sums grand totals correctly', function () {
    ppMakeApprovedBill($this, 150, 2);
    ppMakeApprovedBill($this, 80, 1);

    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.total_amount'))->toEqual(380.0);
});

it('PERF-001: dashboard total_paid and total_unpaid reflect approved allocations', function () {
    $bill = ppMakeApprovedBill($this, 200, 1);
    ppAllocatePayment($this, $bill, 150);

    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.total_paid'))->toEqual(150.0)
        ->and($response->json('data.summary.total_unpaid'))->toEqual(50.0);
});

it('PERF-001: dashboard overdue_amount counts only past-due bills with a balance', function () {
    // Overdue with 150 remaining
    $overdueBill = ppMakeApprovedBill($this, 200, 1, '2026-01-01');
    ppAllocatePayment($this, $overdueBill, 50);

    // Future due date — not overdue
    ppMakeApprovedBill($this, 100, 1, '2030-12-31');

    // Overdue but fully paid — must not count
    $paidBill = ppMakeApprovedBill($this, 100, 1, '2026-01-01');
    ppAllocatePayment($this, $paidBill, 100);

    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.overdue_amount'))->toEqual(150.0);
});

it('PERF-001: dashboard returns zeros when no approved bills exist', function () {
    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.total_bills'))->toBe(0)
        ->and($response->json('data.summary.total_amount'))->toEqual(0.0)
        ->and($response->json('data.summary.total_unpaid'))->toEqual(0.0)
        ->and($response->json('data.summary.overdue_amount'))->toEqual(0.0);
});

it('PERF-001: dashboard excludes voided bills', function () {
    $bill = ppMakeApprovedBill($this, 300, 1);
    $bill->update(['voided_at' => now()]);

    $response = $this->getJson('/api/admin/purchase-report/dashboard');

    $response->assertOk();
    expect($response->json('data.summary.total_bills'))->toBe(0)
        ->and($response->json('data.summary.total_amount'))->toEqual(0.0);
});

// ─── PERF-002: dueBills filters at SQL level ──────────────────────────────────

it('PERF-002: dueBills omits fully-paid bills', function () {
    $bill = ppMakeApprovedBill($this, 100, 1);
    ppAllocatePayment($this, $bill, 100);

    $this->getJson("/api/admin/bill/due?party_id={$this->supplier->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('PERF-002: dueBills includes partially-paid bills with correct due_amount', function () {
    $bill = ppMakeApprovedBill($this, 200, 1);
    ppAllocatePayment($this, $bill, 80);

    $response = $this->getJson("/api/admin/bill/due?party_id={$this->supplier->id}");

    $response->assertOk();
    $rows = $response->json('data');
    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['grand_total'])->toBe(200.0)
        ->and((float) $rows[0]['paid_total'])->toBe(80.0)
        ->and((float) $rows[0]['due_amount'])->toBe(120.0);
});

it('PERF-002: dueBills includes unpaid bills with correct grand_total', function () {
    ppMakeApprovedBill($this, 150, 2);

    $response = $this->getJson("/api/admin/bill/due?party_id={$this->supplier->id}");

    $response->assertOk();
    $rows = $response->json('data');
    expect($rows)->toHaveCount(1)
        ->and((float) $rows[0]['grand_total'])->toBe(300.0)
        ->and((float) $rows[0]['due_amount'])->toBe(300.0);
});

it('PERF-002: dueBills excludes bills from another company even with the same party_id', function () {
    $otherFy = FiscalYear::create([
        'year_name' => '2026-X', 'year_code' => '26X',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $otherCompany = Company::create([
        'fiscal_year_id' => $otherFy->id,
        'company_name' => 'Other Co PP',
        'code' => 'OTHPP',
    ]);

    Bill::withoutGlobalScopes()->create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-FOREIGN-PP-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->approver->id,
    ]);

    $this->getJson("/api/admin/bill/due?party_id={$this->supplier->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('PERF-002: dueBills returns 422 when party_id is missing', function () {
    $this->getJson('/api/admin/bill/due')->assertUnprocessable();
});

// ─── PERM-002: maker-checker for bill approval ───────────────────────────────

it('PERM-002: the bill creator cannot approve their own bill', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-MC-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    // Acting as the same user who created the bill
    $response = $this->postJson("/api/admin/bill/{$bill->id}/approve");

    $response->assertUnprocessable();
    $response->assertJsonPath('message', fn ($msg) => str_contains($msg, 'maker-checker'));
    expect($bill->fresh()->status)->toBe(StatusEnum::DRAFT);
});

it('PERM-002: a different user can approve the bill successfully', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-MC2-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    AccountSetting::create([
        'company_id' => $this->company->id,
        'supplier_account_id' => $this->account->id,
        'purchase_account_id' => $this->account->id,
        'inventory_account_id' => $this->account->id,
    ]);

    // Act as the approver — a different user from the creator
    Sanctum::actingAs($this->approver, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $response = $this->postJson("/api/admin/bill/{$bill->id}/approve");

    $response->assertOk();
    expect($bill->fresh()->status)->toBe(StatusEnum::APPROVED)
        ->and($bill->fresh()->approve_user_id)->toBe($this->approver->id);
});
