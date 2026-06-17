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
use App\Models\PaymentMode;
use App\Enums\PartyTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\PurchaseOrder;
use App\Models\ProductVariant;
use App\Services\TenantService;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function pafWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function pafBillPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'bill_date' => '2026-06-15',
        'party_id' => $test->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'tax_line_type' => 'taxable',
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ], $overrides);
}

function pafDebitNotePayload(object $test, array $overrides = []): array
{
    return array_merge([
        'debit_note_date' => '2026-06-15',
        'party_id' => $test->supplier->id,
        'status' => StatusEnum::DRAFT->value,
        'order_discount_type' => 'fixed',
        'order_discount_value' => 0,
        'items' => [[
            'product_variant_id' => $test->variant->id,
            'warehouse_id' => $test->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ], $overrides);
}

function pafPaymentPayload(object $test, int $billId, float $amount, array $overrides = []): array
{
    return array_merge([
        'payment_date' => '2026-06-15',
        'party_id' => $test->supplier->id,
        'payment_mode_id' => $test->paymentMode->id,
        'account_id' => $test->account->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [[
            'payable_type' => 'bill',
            'payable_id' => $billId,
            'amount' => $amount,
        ]],
    ], $overrides);
}

function pafMakeApprovedBill(object $test, float $rate = 100, int $qty = 1): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'bill_no' => 'BILL-PAF-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $test->user->id,
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'warehouse_id' => $test->warehouse->id,
        'quantity' => $qty,
        'rate' => $rate,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    return $bill;
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    pafWarmCache();
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
        'company_name' => 'PAF Test Co',
        'code' => 'PAFCO',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'PAF Tester',
        'email' => 'paf-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'PAF Supplier',
        'code' => 'PAF-SUP-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
        'pan' => '123456789',
    ]);

    $this->warehouse = Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'PAF Warehouse',
        'code' => 'PAF-W',
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'PAF Widget',
        'code' => 'PAF-PROD-'.uniqid(),
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'PAF-SKU-'.uniqid(),
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'PAF Cash',
        'code' => 'PAF-CASH-'.uniqid(),
    ]);

    $this->paymentMode = PaymentMode::create([
        'company_id' => $this->company->id,
        'name' => 'Cash',
        'is_active' => true,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

// ─── SCH migrations ──────────────────────────────────────────────────────────

it('payments table has voided_at column', function () {
    expect(Schema::hasColumn('payments', 'voided_at'))->toBeTrue();
});

it('bills table has supplier_pan column', function () {
    expect(Schema::hasColumn('bills', 'supplier_pan'))->toBeTrue();
});

it('debit_notes table has supplier_pan column', function () {
    expect(Schema::hasColumn('debit_notes', 'supplier_pan'))->toBeTrue();
});

it('bill_items quantity column is decimal', function () {
    $columns = collect(Schema::getColumns('bill_items'));
    $qty = $columns->firstWhere('name', 'quantity');
    expect($qty)->not->toBeNull();
    // SQLite reports decimal columns as "numeric"; MySQL/Postgres report "decimal"
    $typeName = strtolower($qty['type_name'] ?? $qty['type'] ?? '');
    expect($typeName === 'decimal' || $typeName === 'numeric')->toBeTrue();
});

// ─── BUG-001: draft payment must not set approve_user_id ─────────────────────

it('BUG-001: creating a draft payment does not set approve_user_id', function () {
    $bill = pafMakeApprovedBill($this);

    $response = $this->postJson('/api/admin/payment', pafPaymentPayload($this, $bill->id, 100));

    $response->assertCreated();

    $payment = Payment::latest()->first();
    expect($payment->status)->toBe(StatusEnum::DRAFT)
        ->and($payment->approve_user_id)->toBeNull()
        ->and($payment->approved_at)->toBeNull();
});

it('BUG-001: creating an approved payment sets approve_user_id', function () {
    $bill = pafMakeApprovedBill($this);

    // Approved payments post to GL, which requires AccountSetting::supplier_account_id
    \App\Models\AccountSetting::create([
        'company_id' => $this->company->id,
        'supplier_account_id' => $this->account->id,
    ]);

    $response = $this->postJson('/api/admin/payment', pafPaymentPayload($this, $bill->id, 100, [
        'status' => StatusEnum::APPROVED->value,
    ]));

    $response->assertCreated();

    $payment = Payment::latest()->first();
    expect($payment->status)->toBe(StatusEnum::APPROVED)
        ->and($payment->approve_user_id)->toBe($this->user->id)
        ->and($payment->approved_at)->not->toBeNull();
});

// ─── BUG-004: approved purchase order cannot be deleted ──────────────────────

it('BUG-004: deleting a draft purchase order succeeds', function () {
    $po = PurchaseOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'order_no' => 'PO-PAF-'.uniqid(),
        'order_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    $this->deleteJson("/api/admin/purchase-order/{$po->id}")->assertOk();

    expect(PurchaseOrder::withTrashed()->find($po->id)?->deleted_at)->not->toBeNull();
});

it('BUG-004: deleting an approved purchase order returns 422', function () {
    $po = PurchaseOrder::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'order_no' => 'PO-PAF-'.uniqid(),
        'order_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    $this->deleteJson("/api/admin/purchase-order/{$po->id}")->assertUnprocessable();

    expect(PurchaseOrder::find($po->id))->not->toBeNull();
});

// ─── BUG-005: bill delete clears payment allocations ─────────────────────────

it('BUG-005: deleting a draft bill also deletes its payment allocations', function () {
    $bill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-DEL-'.uniqid(),
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
    ]);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-DEL-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => 100,
    ]);

    expect(PaymentAllocation::where('payable_id', $bill->id)->count())->toBe(1);

    $this->deleteJson("/api/admin/bill/{$bill->id}")->assertOk();

    expect(PaymentAllocation::withTrashed()->where('payable_id', $bill->id)->whereNull('deleted_at')->count())->toBe(0);
});

// ─── BUG-006: debit note order discount stored correctly ─────────────────────

it('BUG-006: debit note with a fixed order discount stores the correct discount.amount', function () {
    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 15,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertCreated();

    $debitNote = DebitNote::latest()->first();
    $discount = \Illuminate\Support\Facades\DB::table('discounts')
        ->where('discountable_type', DebitNote::class)
        ->where('discountable_id', $debitNote->id)
        ->first();

    expect((float) $discount->amount)->toBe(15.0);
});

it('BUG-006: debit note with a percent order discount stores the computed discount.amount', function () {
    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'order_discount_type' => 'percent',
        'order_discount_value' => 10,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 2,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertCreated();

    $debitNote = DebitNote::latest()->first();
    $discount = \Illuminate\Support\Facades\DB::table('discounts')
        ->where('discountable_type', DebitNote::class)
        ->where('discountable_id', $debitNote->id)
        ->first();

    // 2 × 100 = 200 gross; 10% → 20
    expect((float) $discount->amount)->toBe(20.0);
});

// ─── IRD-003: supplier PAN snapshot captured on create ───────────────────────

it('IRD-003: creating a bill snapshots the supplier PAN', function () {
    $response = $this->postJson('/api/admin/bill', pafBillPayload($this));

    $response->assertCreated();

    $bill = Bill::latest()->first();
    expect($bill->supplier_pan)->toBe('123456789');
});

it('IRD-003: creating a debit note snapshots the supplier PAN', function () {
    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this));

    $response->assertCreated();

    $debitNote = DebitNote::latest()->first();
    expect($debitNote->supplier_pan)->toBe('123456789');
});

it('IRD-003: bill supplier_pan is null when supplier has no PAN', function () {
    $noPanSupplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'No PAN Supplier',
        'code' => 'NO-PAN-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $response = $this->postJson('/api/admin/bill', pafBillPayload($this, [
        'party_id' => $noPanSupplier->id,
    ]));

    $response->assertCreated();

    $bill = Bill::latest()->first();
    expect($bill->supplier_pan)->toBeNull();
});

// ─── BL-005: payment void ────────────────────────────────────────────────────

it('BL-005: voiding a draft payment returns 422', function () {
    $bill = pafMakeApprovedBill($this);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-VOID-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => 100,
    ]);

    $this->postJson("/api/admin/payment/{$payment->id}/void")->assertUnprocessable();
    expect($payment->fresh()->voided_at)->toBeNull();
});

it('BL-005: voiding an approved payment sets voided_at and deletes allocations', function () {
    $bill = pafMakeApprovedBill($this);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-VOID2-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => 100,
    ]);

    $response = $this->postJson("/api/admin/payment/{$payment->id}/void");

    $response->assertOk();

    $fresh = $payment->fresh();
    expect($fresh->voided_at)->not->toBeNull();

    $allocationCount = PaymentAllocation::where('payment_id', $payment->id)->count();
    expect($allocationCount)->toBe(0);
});

it('BL-005: voiding an already-voided payment returns 200 without re-voiding', function () {
    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-VOID3-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'voided_at' => now()->subMinute(),
        'status' => StatusEnum::APPROVED,
    ]);

    $firstVoidedAt = $payment->voided_at;

    $response = $this->postJson("/api/admin/payment/{$payment->id}/void");

    $response->assertOk();
    expect($payment->fresh()->voided_at->timestamp)->toBe($firstVoidedAt->timestamp);
});

it('BL-005: deleting an approved non-voided payment returns 422', function () {
    $bill = pafMakeApprovedBill($this);

    $payment = Payment::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'payment_no' => 'PP-DEL2-'.uniqid(),
        'payment_date' => '2026-06-15',
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'gross_amount' => 100,
        'create_user_id' => $this->user->id,
        'approve_user_id' => $this->user->id,
        'approved_at' => now(),
        'status' => StatusEnum::APPROVED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => 100,
    ]);

    $this->deleteJson("/api/admin/payment/{$payment->id}")->assertUnprocessable();
    expect($payment->fresh())->not->toBeNull();
});

// ─── BL-001: debit note item validation against linked bill ──────────────────

it('BL-001: debit note referencing a bill with a different product fails validation', function () {
    $bill = pafMakeApprovedBill($this);

    $otherProduct = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Other Product',
        'code' => 'OTHER-'.uniqid(),
    ]);
    $otherVariant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $otherProduct->id,
        'sku' => 'OTHER-SKU-'.uniqid(),
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'bill_id' => $bill->id,
        'items' => [[
            'product_variant_id' => $otherVariant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 1,
            'rate' => 50,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertUnprocessable();
    // Laravel stores nested array validation errors as flat dotted keys in the errors object
    $errors = $response->json('errors');
    expect($errors['items.0.product_variant_id'][0] ?? '')->toContain('not on the selected bill');
});

it('BL-001: debit note return quantity exceeding billed quantity fails validation', function () {
    $bill = pafMakeApprovedBill($this, 100, 2);

    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'bill_id' => $bill->id,
        'items' => [[
            'product_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'rate' => 100,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'line_discount_type' => 'fixed',
            'line_discount_value' => 0,
        ]],
    ]));

    $response->assertUnprocessable();
    $errors = $response->json('errors');
    expect($errors['items.0.quantity'][0] ?? '')->toContain('exceeds the quantity');
});

it('BL-001: debit note without a bill_id skips item-vs-bill validation', function () {
    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this));

    $response->assertCreated();
});

it('BL-001: debit note referencing a draft (non-approved) bill fails validation', function () {
    $draftBill = Bill::create([
        'company_id' => $this->company->id,
        'fiscal_year_id' => $this->fiscalYear->id,
        'party_id' => $this->supplier->id,
        'bill_no' => 'BILL-DRAFT-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::DRAFT,
    ]);

    BillItem::create([
        'bill_id' => $draftBill->id,
        'product_variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
    ]);

    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'bill_id' => $draftBill->id,
    ]));

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.bill_id.0', fn ($msg) => str_contains($msg, 'valid approved bill'));
});

// ─── SEC-001: payment allocation company scope ───────────────────────────────

it('SEC-001: payment cannot allocate to a bill belonging to another company', function () {
    $otherFy = FiscalYear::create([
        'year_name' => '2026-OTHER',
        'year_code' => '26O',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $otherCompany = Company::create([
        'fiscal_year_id' => $otherFy->id,
        'company_name' => 'Other Co',
        'code' => 'OTHERCO',
    ]);

    $otherSupplier = Party::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Supplier',
        'code' => 'SEC-SUP-'.uniqid(),
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $otherBill = Bill::create([
        'company_id' => $otherCompany->id,
        'fiscal_year_id' => $otherFy->id,
        'party_id' => $otherSupplier->id,
        'bill_no' => 'BILL-OTHER-'.uniqid(),
        'bill_date' => '2026-06-15',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
        'approve_user_id' => $this->user->id,
    ]);

    $response = $this->postJson('/api/admin/payment', [
        'payment_date' => '2026-06-15',
        'party_id' => $this->supplier->id,
        'payment_mode_id' => $this->paymentMode->id,
        'account_id' => $this->account->id,
        'currency_code' => 'NPR',
        'exchange_rate' => 1,
        'status' => StatusEnum::DRAFT->value,
        'allocations' => [[
            'payable_type' => 'bill',
            'payable_id' => $otherBill->id,
            'amount' => 100,
        ]],
    ]);

    $response->assertUnprocessable();
    $errors = $response->json('errors');
    expect($errors['allocations.0.payable_id'][0] ?? '')->toBe('Invalid payable id.');
});

// ─── VAL-001: fiscal year date validation ────────────────────────────────────

it('VAL-001: bill date outside fiscal year is rejected', function () {
    $response = $this->postJson('/api/admin/bill', pafBillPayload($this, [
        'bill_date' => '2025-12-31',
    ]));

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.bill_date.0', fn ($msg) => str_contains($msg, 'fiscal year'));
});

it('VAL-001: bill date within fiscal year is accepted', function () {
    $response = $this->postJson('/api/admin/bill', pafBillPayload($this, [
        'bill_date' => '2026-03-15',
    ]));

    $response->assertCreated();
});

it('VAL-001: debit note date outside fiscal year is rejected', function () {
    $response = $this->postJson('/api/admin/debit-note', pafDebitNotePayload($this, [
        'debit_note_date' => '2027-01-01',
    ]));

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.debit_note_date.0', fn ($msg) => str_contains($msg, 'fiscal year'));
});

it('VAL-001: payment date outside fiscal year is rejected', function () {
    $bill = pafMakeApprovedBill($this);

    $response = $this->postJson('/api/admin/payment', pafPaymentPayload($this, $bill->id, 100, [
        'payment_date' => '2025-01-01',
    ]));

    $response->assertUnprocessable();
    $response->assertJsonPath('errors.payment_date.0', fn ($msg) => str_contains($msg, 'fiscal year'));
});

it('VAL-001: fiscal year validation passes through when company has no fiscal year set', function () {
    $this->company->update(['fiscal_year_id' => null]);

    $response = $this->postJson('/api/admin/bill', pafBillPayload($this, [
        'bill_date' => '2020-01-01',
    ]));

    // No fiscal year configured → rule passes through (no validation error on bill_date)
    $billDateErrors = $response->json('errors')['bill_date'] ?? null;
    expect($billDateErrors)->toBeNull();
});

// ─── BUG-007: morph-alias discount duplicate key ──────────────────────────────

it('BUG-007: updating a bill does not create a duplicate discount row', function () {
    $response = $this->postJson('/api/admin/bill', pafBillPayload($this));
    $response->assertCreated();

    $bill = Bill::latest()->first();

    // Second save (update) must update the existing discount row, not insert a new one
    $updateResponse = $this->putJson("/api/admin/bill/{$bill->id}", pafBillPayload($this, [
        'order_discount_type' => 'fixed',
        'order_discount_value' => 5,
    ]));

    $updateResponse->assertOk();

    expect(\App\Models\Discount::where('discountable_type', $bill->getMorphClass())
        ->where('discountable_id', $bill->id)
        ->count())->toBe(1);
});
