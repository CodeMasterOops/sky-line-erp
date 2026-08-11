<?php

use App\Models\User;
use App\Models\Batch;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Product;
use App\Models\FollowUp;
use App\Models\Warehouse;
use App\Enums\UserTypeEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\BatchStatusEnum;
use App\Models\ProductVariant;
use App\Models\CompanyCategory;
use App\Services\TenantService;
use Illuminate\Support\Facades\Notification;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 2 — background work follows the module switch too
| (docs/saas-modular-platform-and-gym-module-plan.md §3.7 point 3).
|
| Gating only the HTTP layer would leave a disabled module still emailing
| reminders and still mutating rows on a schedule. Commands filter by the set of
| companies that actually run their module.
*/

beforeEach(function () {
    $this->service = app(CompanyModuleService::class);

    $this->withCrm = makeCompany('With CRM', 'WITH');
    $this->withCrm->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'crm'])->create()->id,
    ]);

    $this->withoutCrm = makeCompany('Without CRM', 'WITHOUT');
    $this->withoutCrm->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory'])->create()->id,
    ]);
});

function branchFor(App\Models\Company $company): Branch
{
    return Branch::create([
        'company_id' => $company->id,
        'name' => 'Head Office',
        'code' => 'HO',
        'is_head_office' => true,
    ]);
}

function dueFollowUpFor(App\Models\Company $company): FollowUp
{
    $branch = branchFor($company);

    TenantService::setCompanyId($company->id);
    TenantService::setBranchId($branch->id);

    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Owner',
        'email' => 'owner'.$company->id.'@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    $party = Party::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'type' => PartyTypeEnum::CUSTOMER,
        'name' => 'Customer '.$company->id,
        'code' => 'CUST-'.$company->id,
    ]);

    return FollowUp::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'party_id' => $party->id,
        'user_id' => $user->id,
        'scheduled_at' => now()->subHour(),
    ]);
}

it('only sends crm reminders to companies running the crm module', function () {
    Notification::fake();

    $reminded = dueFollowUpFor($this->withCrm);
    $skipped = dueFollowUpFor($this->withoutCrm);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('crm:dispatch-reminders')->assertSuccessful();

    expect($reminded->fresh()->reminded_at)->not->toBeNull()
        ->and($skipped->fresh()->reminded_at)->toBeNull();
});

it('stops reminding a company as soon as crm is switched off', function () {
    Notification::fake();

    $followUp = dueFollowUpFor($this->withCrm);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->service->disable($this->withCrm->fresh(), 'crm');

    $this->artisan('crm:dispatch-reminders')->assertSuccessful();

    expect($followUp->fresh()->reminded_at)->toBeNull();
});

it('does nothing at all when no company runs the module', function () {
    Notification::fake();

    $this->service->disable($this->withCrm->fresh(), 'crm');

    $this->artisan('crm:dispatch-reminders')
        ->expectsOutputToContain('No company has the CRM module enabled.')
        ->assertSuccessful();
});

it('only expires batches for companies running the inventory module', function () {
    $expired = expiredBatchFor($this->withCrm);

    $this->service->disable($this->withoutCrm->fresh(), 'inventory', cascade: true);
    $untouched = expiredBatchFor($this->withoutCrm);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('batch:expire')->assertSuccessful();

    expect($expired->fresh()->status)->toBe(BatchStatusEnum::Expired)
        ->and($untouched->fresh()->status)->toBe(BatchStatusEnum::Active);
});

function expiredBatchFor(App\Models\Company $company): Batch
{
    $branch = Branch::query()->where('company_id', $company->id)->first() ?? branchFor($company);

    TenantService::setCompanyId($company->id);
    TenantService::setBranchId($branch->id);

    $product = Product::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => 'Perishable '.$company->id,
        'code' => 'PROD-'.$company->id,
        'type' => App\Enums\ProductTypeEnum::PRODUCT,
    ]);

    $variant = ProductVariant::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'sku' => 'SKU-'.$company->id,
    ]);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => 'Main '.$company->id,
        'code' => 'WH-'.$company->id,
    ]);

    return Batch::create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'batch_no' => 'B-'.$company->id,
        'expiry_date' => now()->subDay()->toDateString(),
        'status' => BatchStatusEnum::Active,
    ]);
}

it('only snapshots inventory valuation for companies running inventory', function () {
    // `--replace` deletes and re-inserts rows, so an ungated run is a disabled
    // module writing to the database every month.
    $this->service->disable($this->withoutCrm->fresh(), 'inventory', cascade: true);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('inventory:valuation-snapshot', ['--replace' => true])->assertSuccessful();

    expect(App\Models\InventoryValuationSnapshot::withoutGlobalScopes()->where('company_id', $this->withoutCrm->id)->exists())
        ->toBeFalse();
});

it('does nothing at all when no company runs inventory', function () {
    $this->service->disable($this->withCrm->fresh(), 'inventory', cascade: true);
    $this->service->disable($this->withoutCrm->fresh(), 'inventory', cascade: true);

    TenantService::setCompanyId(null);

    $this->artisan('inventory:gl-reconcile')
        ->expectsOutputToContain('No company has the [inventory] module enabled')
        ->assertSuccessful();

    $this->artisan('products:prune-orphan-variants')
        ->expectsOutputToContain('No company has the [inventory] module enabled')
        ->assertSuccessful();

    $this->artisan('inventory:valuation-snapshot')
        ->expectsOutputToContain('No company has the [inventory] module enabled')
        ->assertSuccessful();
});

it('never prunes the variants of a company that switched inventory off', function () {
    $branch = Branch::query()->where('company_id', $this->withoutCrm->id)->first() ?? branchFor($this->withoutCrm);

    TenantService::setCompanyId($this->withoutCrm->id);
    TenantService::setBranchId($branch->id);

    $product = Product::create([
        'company_id' => $this->withoutCrm->id,
        'branch_id' => $branch->id,
        'name' => 'Multi variant',
        'code' => 'MV-1',
        'type' => App\Enums\ProductTypeEnum::PRODUCT,
    ]);

    $variants = collect(['A', 'B'])->map(fn (string $suffix) => ProductVariant::create([
        'company_id' => $this->withoutCrm->id,
        'branch_id' => $branch->id,
        'product_id' => $product->id,
        'sku' => 'MV-1-'.$suffix,
        'is_default' => $suffix === 'A',
    ]));

    $this->service->disable($this->withoutCrm->fresh(), 'inventory', cascade: true);

    TenantService::setCompanyId(null);
    TenantService::setBranchId(null);

    $this->artisan('products:prune-orphan-variants', ['--apply' => true])->assertSuccessful();

    expect(ProductVariant::withoutGlobalScopes()->whereIn('id', $variants->pluck('id'))->count())->toBe(2);
});
