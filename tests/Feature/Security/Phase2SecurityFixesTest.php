<?php

use App\Models\User;
use App\Models\Party;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\Company;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\PosSession;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\DataTransferJob;
use App\Models\PosCashMovement;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferDirectionEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p2secWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function p2secMakeCompany(string $suffix): array
{
    $fy = FiscalYear::create([
        'year_name' => "2026{$suffix}",
        'year_code' => "26{$suffix}",
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => "Company {$suffix}",
        'code' => "P2S-{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => "Admin {$suffix}",
        'email' => "p2s-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$company, $admin, $fy];
}

beforeEach(function () {
    p2secWarmCache();
    TenantService::reset();
});

// ─────────────────────────────────────────────────────────────────────────────
// 2.1 — UserPolicy: Gate::authorize() replaces abort_if in UserController
// ─────────────────────────────────────────────────────────────────────────────

it('policy: blocks viewing a user from another company via Gate', function () {
    [$companyA, $adminA] = p2secMakeCompany('P2UA');
    [$companyB] = p2secMakeCompany('P2UB');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User',
        'email' => 'p2-fuser-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->getJson("/api/admin/user/{$userB->id}")
        ->assertForbidden();
});

it('policy: allows viewing own-company user via Gate', function () {
    [$companyA, $adminA] = p2secMakeCompany('P2UC');

    $ownUser = User::create([
        'company_id' => $companyA->id,
        'name' => 'Own Staff',
        'email' => 'p2-ownstaff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->getJson("/api/admin/user/{$ownUser->id}")
        ->assertSuccessful();
});

it('policy: blocks deleting a user from another company via Gate', function () {
    [$companyA, $adminA] = p2secMakeCompany('P2UD');
    [$companyB] = p2secMakeCompany('P2UE');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign User Del',
        'email' => 'p2-fdel-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->deleteJson("/api/admin/user/{$userB->id}")
        ->assertForbidden();
});

it('policy: blocks toggling status of a user from another company via Gate', function () {
    [$companyA, $adminA] = p2secMakeCompany('P2UF');
    [$companyB] = p2secMakeCompany('P2UG');

    $userB = User::create([
        'company_id' => $companyB->id,
        'name' => 'Foreign Status User',
        'email' => 'p2-fstatus-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $this->putJson("/api/admin/user/{$userB->id}/update-status")
        ->assertForbidden();
});

// ─────────────────────────────────────────────────────────────────────────────
// 2.3 — MultiTenant on DataTransferJob: API index scoped to own company
// ─────────────────────────────────────────────────────────────────────────────

it('data transfer job index only returns own-company jobs', function () {
    [$companyA, $adminA, $fyA] = p2secMakeCompany('P2DA');
    [$companyB, , $fyB] = p2secMakeCompany('P2DB');

    TenantService::setCompanyId($companyA->id);
    $jobA = DataTransferJob::create([
        'company_id' => $companyA->id,
        'user_id' => $adminA->id,
        'direction' => DataTransferDirectionEnum::Export,
        'entity_type' => DataTransferEntityTypeEnum::Invoice,
        'status' => DataTransferStatusEnum::Uploaded,
    ]);
    TenantService::reset();

    TenantService::setCompanyId($companyB->id);
    $jobB = DataTransferJob::create([
        'company_id' => $companyB->id,
        'user_id' => $adminA->id,
        'direction' => DataTransferDirectionEnum::Export,
        'entity_type' => DataTransferEntityTypeEnum::Invoice,
        'status' => DataTransferStatusEnum::Uploaded,
    ]);
    TenantService::reset();

    // Query with company A context — must not see company B job
    TenantService::setCompanyId($companyA->id);
    $visible = DataTransferJob::all()->pluck('id');
    TenantService::reset();

    expect($visible)->toContain($jobA->id)
        ->and($visible)->not->toContain($jobB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2.3 — MultiTenant on PosCashMovement: scoped to own company
// ─────────────────────────────────────────────────────────────────────────────

it('pos cash movement query is scoped to own company', function () {
    [$companyA, $adminA] = p2secMakeCompany('P2PA');
    [$companyB, $adminB] = p2secMakeCompany('P2PB');

    $branchA = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch A', 'code' => 'BRA-'.uniqid()]);
    $branchB = Branch::create(['company_id' => $companyB->id, 'name' => 'Branch B', 'code' => 'BRB-'.uniqid()]);

    TenantService::setCompanyId($companyA->id);
    $sessionA = PosSession::create([
        'company_id' => $companyA->id,
        'branch_id' => $branchA->id,
        'user_id' => $adminA->id,
        'opening_cash' => 100,
        'status' => 'open',
        'opened_at' => now(),
    ]);
    $movementA = PosCashMovement::create([
        'pos_session_id' => $sessionA->id,
        'company_id' => $companyA->id,
        'user_id' => $adminA->id,
        'type' => 'cash_in',
        'amount' => 50,
        'reason' => 'Test A',
    ]);
    TenantService::reset();

    TenantService::setCompanyId($companyB->id);
    $sessionB = PosSession::create([
        'company_id' => $companyB->id,
        'branch_id' => $branchB->id,
        'user_id' => $adminB->id,
        'opening_cash' => 200,
        'status' => 'open',
        'opened_at' => now(),
    ]);
    $movementB = PosCashMovement::create([
        'pos_session_id' => $sessionB->id,
        'company_id' => $companyB->id,
        'user_id' => $adminB->id,
        'type' => 'cash_in',
        'amount' => 75,
        'reason' => 'Test B',
    ]);
    TenantService::reset();

    TenantService::setCompanyId($companyA->id);
    $visible = PosCashMovement::all()->pluck('id');
    TenantService::reset();

    expect($visible)->toContain($movementA->id)
        ->and($visible)->not->toContain($movementB->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2.4 — BudgetController uses auth('admin') guard
// ─────────────────────────────────────────────────────────────────────────────

it('budget store uses the admin guard for fiscal year resolution', function () {
    [$companyA, $adminA, $fyA] = p2secMakeCompany('P2BA');

    // The company must have a fiscal year set
    $companyA->update(['fiscal_year_id' => $fyA->id]);

    // Provide a minimal account for the budget line
    TenantService::setCompanyId($companyA->id);
    $account = \App\Models\Account::create([
        'company_id' => $companyA->id,
        'name' => 'Test Account Budget',
        'code' => 'TAB-'.uniqid(),
    ]);
    TenantService::reset();

    Sanctum::actingAs($adminA, ['*'], 'admin');

    $response = $this->postJson('/api/admin/budget', [
        'name' => 'Q1 Budget',
        'lines' => [
            [
                'account_id' => $account->id,
                'budgeted_amount' => 5000,
            ],
        ],
    ]);

    $response->assertCreated();

    $budget = Budget::withoutGlobalScopes()->find($response->json('data.id'));
    expect($budget->company_id)->toBe($companyA->id)
        ->and($budget->fiscal_year_id)->toBe($fyA->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2.6 — POS cross-branch return prevention
// ─────────────────────────────────────────────────────────────────────────────

it('pos return blocks returning an invoice from a different branch session', function () {
    [$companyA, $adminA, $fyA] = p2secMakeCompany('P2RA');

    $branchA = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch RA', 'code' => 'BRA-RA-'.uniqid()]);
    $branchB = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch RB', 'code' => 'BRA-RB-'.uniqid()]);

    TenantService::setCompanyId($companyA->id);

    // Invoice belongs to branch B
    $invoiceB = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchB->id,
        'invoice_no' => 'INV-P2R-001',
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    // Open POS session for branch A
    $session = PosSession::create([
        'company_id' => $companyA->id,
        'branch_id' => $branchA->id,
        'user_id' => $adminA->id,
        'opening_cash' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    TenantService::reset();

    Sanctum::actingAs($adminA, ['*'], 'admin');

    // Attempt to return an invoice that belongs to branch B while session is on branch A
    $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $invoiceB->id,
        'items' => [
            [
                'product_variant_id' => 1,
                'warehouse_id' => 1,
                'quantity' => 1,
                'rate' => 10,
            ],
        ],
    ])->assertForbidden();
});

it('pos return allows returning an invoice from the same branch session', function () {
    [$companyA, $adminA, $fyA] = p2secMakeCompany('P2RB');

    $branchA = Branch::create(['company_id' => $companyA->id, 'name' => 'Branch RA2', 'code' => 'BRA-RA2-'.uniqid()]);

    TenantService::setCompanyId($companyA->id);

    $party = Party::create(['company_id' => $companyA->id, 'name' => 'POS Party', 'code' => 'POSP-'.uniqid(), 'type' => 'customer']);

    // Invoice belongs to branch A
    $invoice = Invoice::create([
        'company_id' => $companyA->id,
        'fiscal_year_id' => $fyA->id,
        'branch_id' => $branchA->id,
        'party_id' => $party->id,
        'invoice_no' => 'INV-P2R-002',
        'invoice_date' => now()->toDateString(),
        'status' => StatusEnum::APPROVED,
        'create_user_id' => $adminA->id,
    ]);

    // Open POS session for branch A
    PosSession::create([
        'company_id' => $companyA->id,
        'branch_id' => $branchA->id,
        'user_id' => $adminA->id,
        'opening_cash' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    TenantService::reset();

    Sanctum::actingAs($adminA, ['*'], 'admin');

    // Attempt a return — it'll fail validation (no valid product variant/warehouse)
    // but should NOT return 403 — company + branch match so it proceeds to deeper validation
    $response = $this->postJson('/api/admin/pos/return', [
        'invoice_id' => $invoice->id,
        'items' => [
            [
                'product_variant_id' => 99999,
                'warehouse_id' => 99999,
                'quantity' => 1,
                'rate' => 10,
            ],
        ],
    ]);

    // Validation fails (no valid product variant/warehouse) but NOT 403 — branch check passed
    $response->assertUnprocessable();
    expect($response->json('errors'))->not->toBeEmpty();
});
