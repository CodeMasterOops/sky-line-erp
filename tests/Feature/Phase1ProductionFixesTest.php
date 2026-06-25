<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function phase1FixesWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    $key = allTablesCacheKey();
    Cache::forget($key);
    Cache::forever($key, $tables);
}

beforeEach(function () {
    Cache::flush();
    phase1FixesWarmCache();
    TenantService::reset();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => 'P1-FY', 'year_code' => 'P1',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Phase1 Co',
        'code' => 'P1-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->admin = User::create([
        'company_id' => $this->company->id,
        'name' => 'Admin User',
        'email' => 'p1-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
});

// ─── H-3: POS warehouse cache must be branch-scoped ───────────────────────────

it('caches pos warehouses under branch-specific keys so branch A cache does not bleed into branch B', function () {
    $branchA = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch A', 'code' => 'BA',
    ]);
    $branchB = Branch::create([
        'company_id' => $this->company->id,
        'name' => 'Branch B', 'code' => 'BB',
    ]);

    Warehouse::create([
        'company_id' => $this->company->id,
        'branch_id' => $branchA->id,
        'name' => 'WH-A', 'code' => 'WHA',
    ]);
    Warehouse::create([
        'company_id' => $this->company->id,
        'branch_id' => $branchB->id,
        'name' => 'WH-B', 'code' => 'WHB',
    ]);

    Sanctum::actingAs($this->admin, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    // Request from Branch A — should only see WH-A
    TenantService::setBranchId($branchA->id);
    $responseA = $this->withHeaders(['X-Branch-Id' => $branchA->id])
        ->getJson('/api/admin/pos/warehouses');

    $responseA->assertOk();
    $namesA = collect($responseA->json('data'))->pluck('name')->all();
    expect($namesA)->toContain('WH-A')
        ->and($namesA)->not->toContain('WH-B');

    // Request from Branch B — should only see WH-B, not Branch A's cached result
    TenantService::setBranchId($branchB->id);
    $responseB = $this->withHeaders(['X-Branch-Id' => $branchB->id])
        ->getJson('/api/admin/pos/warehouses');

    $responseB->assertOk();
    $namesB = collect($responseB->json('data'))->pluck('name')->all();
    expect($namesB)->toContain('WH-B')
        ->and($namesB)->not->toContain('WH-A');

    // Confirm both are stored under distinct cache keys
    $companyId = $this->company->id;
    $keyA = "pos_warehouses_{$companyId}_{$branchA->id}";
    $keyB = "pos_warehouses_{$companyId}_{$branchB->id}";
    expect(Cache::has($keyA))->toBeTrue()
        ->and(Cache::has($keyB))->toBeTrue()
        ->and($keyA)->not->toBe($keyB);
});

it('uses the "all" fallback cache key when no branch header is sent', function () {
    Warehouse::create([
        'company_id' => $this->company->id,
        'name' => 'Unscoped WH', 'code' => 'UWH',
    ]);

    Sanctum::actingAs($this->admin, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
    TenantService::setBranchId(null);

    $response = $this->getJson('/api/admin/pos/warehouses');
    $response->assertOk();

    $companyId = $this->company->id;
    expect(Cache::has("pos_warehouses_{$companyId}_all"))->toBeTrue();
});

// ─── H-2: allTables() must work on all DB drivers (no SHOW TABLES) ────────────

it('columnExists returns true for known columns using the cross-db allTables helper', function () {
    // Exercises the unified Schema::getTableListing() path in helper.php.
    // Runs on SQLite in CI; must also work on MySQL/PostgreSQL in production
    // because DB::select('SHOW TABLES') was removed.
    expect(columnExists('users', 'email'))->toBeTrue()
        ->and(columnExists('invoices', 'invoice_no'))->toBeTrue()
        ->and(columnExists('invoices', 'nonexistent_column_xyz'))->toBeFalse();
});

it('columnExists returns false for a nonexistent table without throwing', function () {
    expect(columnExists('totally_fake_table_xyz', 'id'))->toBeFalse();
});
