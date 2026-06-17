<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Services\TenantService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Observers\BelongsToCompanyObserver;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function p4secWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

function p4secMakeCompany(string $suffix): array
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
        'code' => "P4S-{$suffix}",
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $admin = User::create([
        'company_id' => $company->id,
        'name' => "Admin {$suffix}",
        'email' => "p4s-{$suffix}-".uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    return [$company, $admin, $fy];
}

beforeEach(function () {
    p4secWarmCache();
    TenantService::reset();
});

// ─────────────────────────────────────────────────────────────────────────────
// 4.2 — SetTenantContext logs path and user_agent on branch-access-denied
// ─────────────────────────────────────────────────────────────────────────────

it('SetTenantContext branch-access-denied log includes path and user_agent', function () {
    [$companyA, $adminA] = p4secMakeCompany('P4BA');

    // Create a branch in the company and a non-admin user NOT assigned to it.
    // branchBelongsToCompany passes (branch is in their company) but
    // canUserAccessBranch fails → triggers the warning log.
    $branch = Branch::create(['company_id' => $companyA->id, 'name' => 'Unassigned', 'code' => 'P4UA-'.uniqid()]);

    $staff = User::create([
        'company_id' => $companyA->id,
        'name' => 'Staff P4',
        'email' => 'p4-staff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::USER,
    ]);

    Log::spy();

    Sanctum::actingAs($staff, ['*'], 'admin');

    $this->getJson('/api/admin/invoice', ['X-Branch-Id' => (string) $branch->id])
        ->assertForbidden();

    Log::shouldHaveReceived('warning')
        ->withArgs(function (string $message, array $context) {
            return $message === 'branch-access-denied'
                && array_key_exists('path', $context)
                && array_key_exists('user_agent', $context);
        })
        ->once();
});

// ─────────────────────────────────────────────────────────────────────────────
// 4.3 — BelongsToCompanyObserver logs cross-company leak
// ─────────────────────────────────────────────────────────────────────────────

it('BelongsToCompanyObserver logs a critical message on cross-company retrieval', function () {
    [$companyA] = p4secMakeCompany('P4CA');
    [$companyB] = p4secMakeCompany('P4CB');

    Log::spy();

    $observer = new BelongsToCompanyObserver;

    $model = new User(['company_id' => $companyB->id]);
    $model->id = 9999;

    TenantService::setCompanyId($companyA->id);
    $observer->retrieved($model);
    TenantService::reset();

    Log::shouldHaveReceived('critical')
        ->withArgs(fn (string $msg) => str_contains($msg, '[TENANT LEAK]'))
        ->once();
});

it('BelongsToCompanyObserver does not throw in non-local environments', function () {
    [$companyA] = p4secMakeCompany('P4XA');
    [$companyB] = p4secMakeCompany('P4XB');

    BelongsToCompanyObserver::resetLeaks();

    $observer = new BelongsToCompanyObserver;

    $model = new User(['company_id' => $companyB->id]);
    $model->id = 8888;

    TenantService::setCompanyId($companyA->id);

    // The test env is not 'local', so no RuntimeException should be thrown
    expect(fn () => $observer->retrieved($model))->not->toThrow(\RuntimeException::class);

    TenantService::reset();
    BelongsToCompanyObserver::resetLeaks();
});

it('BelongsToCompanyObserver::assertNoLeaks fails when a cross-company retrieval was captured', function () {
    [$companyA] = p4secMakeCompany('P4NA');
    [$companyB] = p4secMakeCompany('P4NB');

    BelongsToCompanyObserver::resetLeaks();

    $observer = new BelongsToCompanyObserver;
    $model = new User(['company_id' => $companyB->id]);
    $model->id = 7777;

    TenantService::setCompanyId($companyA->id);
    $observer->retrieved($model);
    TenantService::reset();

    expect(fn () => BelongsToCompanyObserver::assertNoLeaks())
        ->toThrow(\PHPUnit\Framework\AssertionFailedError::class);

    BelongsToCompanyObserver::resetLeaks();
});

// ─────────────────────────────────────────────────────────────────────────────
// 4.4 — app:check-orphan-rows command
// ─────────────────────────────────────────────────────────────────────────────

it('check-orphan-rows reports zero orphans on a clean database', function () {
    $this->artisan('app:check-orphan-rows --dry-run')
        ->assertSuccessful()
        ->expectsOutputToContain('No orphan rows found');
});
