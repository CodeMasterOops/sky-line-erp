<?php

use Carbon\Carbon;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Services\TenantService;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\AccountingPeriodStatusEnum;
use App\Enums\InventoryCostingMethodEnum;
use App\Services\Accounting\AccountingPeriodGenerator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function generatorWarmAllTablesCache(): void
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
    generatorWarmAllTablesCache();

    // Nepali fiscal year 2082-83: Shrawan 1 2082 (2025-07-16) → Ashadh end 2083 (2026-07-15).
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2082-83', 'year_code' => '82',
        'start_date' => '2025-07-16', 'end_date' => '2026-07-15',
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'BS Period Co', 'code' => 'BSP',
        'inventory_costing_method' => InventoryCostingMethodEnum::FIFO,
    ]);

    TenantService::setCompanyId($this->company->id);
});

function generatedPeriods(object $test)
{
    AccountingPeriodGenerator::generateForCompanyIfMissing($test->company->id, $test->fiscalYear);

    return AccountingPeriod::withoutGlobalScopes()
        ->where('company_id', $test->company->id)
        ->where('fiscal_year_id', $test->fiscalYear->id)
        ->orderBy('period_number')
        ->get();
}

it('generates one period per BS month for a full Nepali fiscal year', function () {
    $periods = generatedPeriods($this);

    expect($periods)->toHaveCount(12);
});

it('names periods with BS month names starting at Shrawan and ending at Ashadh', function () {
    $periods = generatedPeriods($this);

    expect($periods->first()->period_name)->toBe('Shrawan 2082');
    expect($periods->last()->period_name)->toBe('Ashadh 2083');
});

it('slices periods on real BS month boundaries', function () {
    $periods = generatedPeriods($this);

    // Shrawan 2082 = 2025-07-16 → 2025-08-15 (BS month edge, not the AD month edge).
    expect($periods->first()->start_date->toDateString())->toBe('2025-07-16');
    expect($periods->first()->end_date->toDateString())->toBe('2025-08-15');
});

it('clamps the first and last periods to the fiscal year boundaries', function () {
    $periods = generatedPeriods($this);

    expect($periods->first()->start_date->toDateString())->toBe('2025-07-16');
    expect($periods->last()->end_date->toDateString())->toBe('2026-07-15');
});

it('produces contiguous periods with no gaps or overlaps', function () {
    $periods = generatedPeriods($this);

    $periods->sliding(2)->each(function ($pair) {
        [$current, $next] = [$pair->first(), $pair->last()];
        $expectedNextStart = Carbon::parse($current->end_date)->addDay()->toDateString();
        expect($next->start_date->toDateString())->toBe($expectedNextStart);
    });
});

it('opens every generated period', function () {
    $periods = generatedPeriods($this);

    expect($periods->pluck('status')->unique()->all())
        ->toBe([AccountingPeriodStatusEnum::OPEN]);
});

it('does not regenerate when periods already exist', function () {
    generatedPeriods($this);
    $countAfterFirst = AccountingPeriod::withoutGlobalScopes()->count();

    AccountingPeriodGenerator::generateForCompanyIfMissing($this->company->id, $this->fiscalYear);

    expect(AccountingPeriod::withoutGlobalScopes()->count())->toBe($countAfterFirst);
});
