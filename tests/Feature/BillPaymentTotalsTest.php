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
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Models\ProductVariant;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Http\Resources\Admin\Purchase\BillResource;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function billPaymentWarmAllTablesCache(): void
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
    billPaymentWarmAllTablesCache();
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Bill Pay Co',
        'code' => 'BPC',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Bill Pay User',
        'email' => 'bill-pay-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $this->supplier = Party::create([
        'company_id' => $this->company->id,
        'name' => 'Supplier',
        'code' => 'SUP-PAY',
        'type' => PartyTypeEnum::SUPPLIER,
    ]);

    $product = Product::create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'code' => 'WIDGET-PAY',
    ]);

    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => $product->id,
        'sku' => 'SKU-PAY-1',
        'purchase_price' => 100,
        'is_default' => true,
    ]);

    $this->account = Account::create([
        'company_id' => $this->company->id,
        'account_group_id' => null,
        'name' => 'Cash',
        'code' => 'CASH-PAY',
    ]);
});

function makeBillWithLine(object $test, StatusEnum $paymentStatus, float $allocationAmount): Bill
{
    $bill = Bill::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'bill_no' => 'BILL-PAY-'.uniqid(),
        'bill_date' => now()->toDateString(),
        'create_user_id' => $test->user->id,
        'status' => StatusEnum::APPROVED,
        'approved_at' => now(),
    ]);

    BillItem::create([
        'bill_id' => $bill->id,
        'product_variant_id' => $test->variant->id,
        'quantity' => 1,
        'rate' => 100,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'tax_line_type' => 'taxable',
    ]);

    $payment = Payment::create([
        'company_id' => $test->company->id,
        'fiscal_year_id' => $test->fiscalYear->id,
        'party_id' => $test->supplier->id,
        'payment_no' => 'PAY-'.uniqid(),
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'account_id' => $test->account->id,
        'gross_amount' => $allocationAmount,
        'create_user_id' => $test->user->id,
        'status' => $paymentStatus,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'payable_type' => (new Bill)->getMorphClass(),
        'payable_id' => $bill->id,
        'amount' => $allocationAmount,
    ]);

    return $bill;
}

it('reports zero due when approved payments fully cover the bill', function () {
    $bill = makeBillWithLine($this, StatusEnum::APPROVED, 100);

    $bill->load(['billItems', 'discount', 'paymentAllocations.payment']);

    $data = BillResource::make($bill)->resolve();

    expect($data['grand_total'])->toBe(100.0)
        ->and($data['paid_total'])->toBe(100.0)
        ->and($data['due_amount'])->toBe(0.0);
});

it('ignores draft payment allocations when calculating due amount', function () {
    $bill = makeBillWithLine($this, StatusEnum::DRAFT, 100);

    $bill->load(['billItems', 'discount', 'paymentAllocations.payment']);

    $data = BillResource::make($bill)->resolve();

    expect($data['paid_total'])->toBe(0.0)
        ->and($data['due_amount'])->toBe(100.0);
});

it('returns null payment totals when allocations are not loaded', function () {
    $bill = makeBillWithLine($this, StatusEnum::APPROVED, 50);

    $bill->load(['billItems', 'discount']);

    $data = BillResource::make($bill)->resolve();

    expect($data['paid_total'])->toBeNull()
        ->and($data['due_amount'])->toBeNull();
});
