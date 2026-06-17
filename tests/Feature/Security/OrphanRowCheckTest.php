<?php

use App\Models\User;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\InventoryCostingMethodEnum;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
});

it('check-orphan-rows detects rows whose company_id does not exist', function () {
    $fy = FiscalYear::create([
        'year_name' => '2026ORF',
        'year_code' => '26ORF',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);
    $company = Company::create([
        'fiscal_year_id' => $fy->id,
        'company_name' => 'Orphan Co',
        'code' => 'ORP-'.uniqid(),
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);
    $user = User::create([
        'company_id' => $company->id,
        'name' => 'Orphan Admin',
        'email' => 'orphan-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    // PRAGMA defer_foreign_keys = ON defers FK checks to transaction commit.
    // RefreshDatabase rolls back (never commits), so the FK violation is never raised.
    // This lets us create an "orphan" row that the command can detect within the test.
    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('users')->where('id', $user->id)->update(['company_id' => 99999]);

    $this->artisan('app:check-orphan-rows --dry-run')
        ->assertFailed()
        ->expectsOutputToContain('users');
});
