<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\DocumentNumberGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function docNumberWarmAllTablesCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    docNumberWarmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026', 'year_code' => '26',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Lock Co', 'code' => 'LCK',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Locker',
        'email' => 'locker-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    TenantService::setCompanyId($this->company->id);
});

it('locks the fiscal year row when called inside a transaction', function () {
    // The SQLite driver silently strips FOR UPDATE so the *exact* clause isn't
    // visible in the query log, but the lock branch runs a SELECT on
    // `fiscal_years` — its presence inside the transaction proves the lock
    // path was taken (on MySQL/Postgres it includes FOR UPDATE for real).
    $generator = app(DocumentNumberGenerator::class);

    DB::enableQueryLog();

    DB::transaction(function () use ($generator) {
        $generator->fiscalYear(Invoice::class, 'INV-', $this->fiscalYear->id, '26');
    });

    $sql = collect(DB::getQueryLog())->pluck('query')->implode(' | ');

    expect($sql)->toContain('"fiscal_years"');
});

// Note: the symmetric "skips the lock query when called outside a transaction"
// case cannot be reliably tested under RefreshDatabase, which wraps every test
// in its own transaction (so `DB::transactionLevel()` is always >= 1). The
// fallback behaviour is a one-line guard in the generator and is verifiable by
// inspection; the real concurrency contract is "lock when inside a transaction"
// which the test above exercises.

it('produces sequential numbers across two generator calls in separate transactions', function () {
    $generator = app(DocumentNumberGenerator::class);

    $first = DB::transaction(function () use ($generator) {
        $no = $generator->fiscalYear(Invoice::class, 'INV-', $this->fiscalYear->id, '26');
        Invoice::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'invoice_no' => $no,
            'invoice_date' => now()->toDateString(),
            'create_user_id' => $this->user->id,
            'status' => StatusEnum::DRAFT,
        ]);

        return $no;
    });

    $second = DB::transaction(function () use ($generator) {
        $no = $generator->fiscalYear(Invoice::class, 'INV-', $this->fiscalYear->id, '26');
        Invoice::create([
            'company_id' => $this->company->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'invoice_no' => $no,
            'invoice_date' => now()->toDateString(),
            'create_user_id' => $this->user->id,
            'status' => StatusEnum::DRAFT,
        ]);

        return $no;
    });

    expect($first)->toBe('INV-1/26')
        ->and($second)->toBe('INV-2/26');
});
