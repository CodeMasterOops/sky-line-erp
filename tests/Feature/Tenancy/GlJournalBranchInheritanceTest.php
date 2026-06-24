<?php

use App\Enums\InventoryCostingMethodEnum;
use App\Enums\JournalTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\StatusEnum;
use App\Enums\UserTypeEnum;
use App\Models\Account;
use App\Models\AccountSetting;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\InvoiceGlPostingService;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function glBranchWarmCache(): void
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
    glBranchWarmCache();
    TenantService::reset();

    $this->fy = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);
    $this->company = Company::create([
        'fiscal_year_id' => $this->fy->id, 'company_name' => 'Co', 'code' => 'GLB-CO',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'A', 'email' => 'glb-'.uniqid().'@t.test',
        'password' => bcrypt('x'), 'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);

    $this->branchHo = Branch::create(['company_id' => $this->company->id, 'name' => 'HO', 'code' => 'GLB-HO', 'is_head_office' => true, 'is_active' => true]);
    $this->branchB = Branch::create(['company_id' => $this->company->id, 'name' => 'B', 'code' => 'GLB-B', 'is_active' => true]);

    $account = Account::create(['company_id' => $this->company->id, 'name' => 'Cash', 'code' => 'GLB-CASH']);
    AccountSetting::create([
        'company_id' => $this->company->id,
        'cash_sales_account_id' => $account->id,
        'bank_sales_account_id' => $account->id,
        'customer_account_id' => $account->id,
        'sales_account_id' => $account->id,
    ]);

    TenantService::setBranchId($this->branchB->id);
    $this->party = Party::create(['company_id' => $this->company->id, 'type' => PartyTypeEnum::CUSTOMER, 'name' => 'C', 'code' => 'GLB-C']);
    $this->variant = ProductVariant::create([
        'company_id' => $this->company->id,
        'product_id' => Product::create(['company_id' => $this->company->id, 'name' => 'W', 'code' => 'GLB-W'])->id,
        'sku' => 'GLB-SKU', 'sales_price' => 100, 'is_default' => true,
    ]);
});

afterEach(fn () => TenantService::reset());

it('stamps the GL journal with the source invoice branch, not the request context branch', function () {
    // Invoice belongs to branch B.
    $invoice = Invoice::create([
        'company_id' => $this->company->id,
        'branch_id' => $this->branchB->id,
        'fiscal_year_id' => $this->fy->id,
        'party_id' => $this->party->id,
        'invoice_no' => 'INV-GLB-1',
        'invoice_date' => '2026-06-01',
        'create_user_id' => $this->user->id,
        'status' => StatusEnum::APPROVED,
        'total_amount' => 100,
    ]);
    InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_variant_id' => $this->variant->id,
        'quantity' => 1, 'rate' => 100, 'discount_amount' => 0, 'tax_amount' => 0,
    ]);

    // Simulate posting under a DIFFERENT request context (head office), e.g. a
    // queued/admin re-post. The journal must still follow the invoice's branch.
    TenantService::setBranchId($this->branchHo->id);

    app(InvoiceGlPostingService::class)->postFromInvoice($invoice);

    $journal = Journal::withoutGlobalScopes()
        ->where('reference_type', $invoice->getMorphClass())
        ->where('reference_id', $invoice->id)
        ->where('type', JournalTypeEnum::INVOICE)
        ->first();

    expect($journal)->not->toBeNull('Invoice GL journal must be posted')
        ->and($journal->branch_id)->toBe($this->branchB->id);
});
