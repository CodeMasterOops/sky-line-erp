<?php

use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use Illuminate\Support\Facades\DB;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 1 — the migration that must change nothing
| (docs/saas-modular-platform-and-gym-module-plan.md §3.13).
|
| Companies that exist when modularity ships keep every module they can use
| today, written as explicit rows. Explicit rows win over category defaults, so
| a null category is harmless — which is what makes it safe for Phase 2's
| middleware to start enforcing afterwards.
|
| RefreshDatabase runs the migration against an empty `companies` table, so the
| backfill is re-run here by hand against companies created in the test.
*/

function runBackfillMigration(): void
{
    $migration = require database_path('migrations/2026_08_09_100100_backfill_company_modules_for_existing_companies.php');

    $migration->up();
}

/**
 * @return list<string>
 */
function backfilledKeys(int $companyId): array
{
    return CompanyModule::query()
        ->where('company_id', $companyId)
        ->orderBy('module_key')
        ->pluck('module_key')
        ->all();
}

it('enables every shipped module for a company that predates modularity', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();

    $keys = backfilledKeys($company->id);

    expect($keys)->not->toBeEmpty()
        ->and(CompanyModule::query()->where('company_id', $company->id)->where('is_enabled', false)->count())->toBe(0)
        ->and(app(CompanyModuleService::class)->enabledKeys($company->id))->toEqualCanonicalizing($keys);
});

it('backfills every company, not just the first', function () {
    $first = makeCompany('First Co', 'FIRST');
    $second = makeCompany('Second Co', 'SECOND');

    runBackfillMigration();

    expect(backfilledKeys($first->id))->toBe(backfilledKeys($second->id))
        ->and(backfilledKeys($second->id))->not->toBeEmpty();
});

it('only backfills modules that actually exist in the registry', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();

    $unknown = array_diff(backfilledKeys($company->id), app(ModuleRegistry::class)->keys());

    expect($unknown)->toBe([]);
});

it('does not hand out industry modules that shipped later', function () {
    // The migration freezes its module list on purpose: re-running it on a
    // fresh database must never switch on a vertical (gym, and whatever comes
    // after it) that did not exist when the backfill was written.
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();

    $industryKeys = array_keys(app(ModuleRegistry::class)->grouped()['industry'] ?? []);

    expect(array_intersect(backfilledKeys($company->id), $industryKeys))->toBe([]);
});

it('marks core as core and everything else as migrated', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();

    $rows = CompanyModule::query()->where('company_id', $company->id)->get();

    expect($rows->firstWhere('module_key', 'core')->source)->toBe(ModuleSourceEnum::Core)
        ->and($rows->firstWhere('module_key', 'sales')->source)->toBe(ModuleSourceEnum::Migration)
        ->and($rows->firstWhere('module_key', 'sales')->enabled_at)->not->toBeNull();
});

it('leaves the company category alone', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();

    expect($company->fresh()->company_category_id)->toBeNull();
});

it('can be re-run without duplicating rows', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    runBackfillMigration();
    $first = CompanyModule::query()->where('company_id', $company->id)->count();

    runBackfillMigration();

    expect(CompanyModule::query()->where('company_id', $company->id)->count())->toBe($first);
});

it('does not overwrite a decision already recorded for the company', function () {
    $company = makeCompany('Legacy Co', 'LEG');

    CompanyModule::query()->create([
        'company_id' => $company->id,
        'module_key' => 'crm',
        'is_enabled' => false,
        'source' => ModuleSourceEnum::Manual,
    ]);

    runBackfillMigration();

    $row = CompanyModule::query()->where('company_id', $company->id)->where('module_key', 'crm')->sole();

    expect($row->is_enabled)->toBeFalse()
        ->and($row->source)->toBe(ModuleSourceEnum::Manual);
});

it('leaves existing plans uncapped so nobody loses a module', function () {
    expect(DB::table('plans')->whereNotNull('modules')->count())->toBe(0);
});
