<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 8 — the soak test
| (saas-modular-platform plan §10.3; capping plan gap F5).
|
| The guarantee no single feature test covers: with a module switched off the
| app still BOOTS and every shared screen still renders — and switching it back
| on returns the company to exactly where it was.
|
| This is the test that would have caught the whole A-family of gaps: a
| dashboard that queried purchase tables for a company without Purchase, and a
| reports hub that offered a module's reports after it was switched off.
*/

beforeEach(function () {
    $this->registry = app(ModuleRegistry::class);
    $this->service = app(CompanyModuleService::class);

    $this->company = makeCompany('Soak Co', 'SOAK');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()
            ->withModules($this->registry->togglableKeys())
            ->create()->id,
    ]);
    $this->company->refresh();

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@soak.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, [], 'admin');
});

/** The screens every company has, whatever its modules. */
function assertSharedScreensStillWork(): void
{
    test()->getJson('/api/admin/dashboard')->assertSuccessful();
    test()->getJson('/api/admin/report-catalogue')->assertSuccessful();
    test()->getJson('/api/admin/module')->assertSuccessful();
    test()->getJson('/api/admin/module/catalogue')->assertSuccessful();
    test()->getJson('/api/admin/profile/permissions')->assertSuccessful();
    test()->getJson('/api/admin/branch')->assertSuccessful();
    test()->getJson('/api/admin/party')->assertSuccessful();
}

it('keeps the shared screens working with every togglable module off', function (string $moduleKey) {
    // Some modules cascade (disabling inventory takes sales and gym with it),
    // which is exactly the state worth soaking.
    $this->service->disable($this->company->fresh(), $moduleKey, cascade: true);

    assertSharedScreensStillWork();
})->with(function (): array {
    // A dataset closure runs before the application boots, so the container is
    // not available — read the manifest itself, which is what the registry
    // reads too, rather than pinning a hand-copied list that would go stale.
    $modules = require dirname(__DIR__, 3).'/config/modules.php';

    return array_values(array_keys(array_filter(
        $modules,
        fn (array $definition): bool => ! ($definition['always_on'] ?? false),
    )));
});

it('keeps the shared screens working with nothing but core', function () {
    foreach ($this->registry->togglableKeys() as $moduleKey) {
        if ($this->service->isEnabled($moduleKey, (int) $this->company->id)) {
            $this->service->disable($this->company->fresh(), $moduleKey, cascade: true);
        }
    }

    expect($this->service->enabledKeys((int) $this->company->id))->toBe(['core']);

    assertSharedScreensStillWork();
});

it('returns the company to exactly where it was when the module comes back', function () {
    $before = $this->service->enabledKeys((int) $this->company->id);
    sort($before);

    $this->service->disable($this->company->fresh(), 'inventory', cascade: true);

    expect($this->service->enabledKeys((int) $this->company->id))->not->toContain('inventory', 'sales');

    // Re-enable exactly what was on before, including everything the cascade
    // took with it (pos, gym and nepal-compliance all sit downstream of the
    // modules that were switched off).
    foreach ($before as $moduleKey) {
        if ($moduleKey !== ModuleRegistry::CORE_KEY) {
            $this->service->enable($this->company->fresh(), $moduleKey);
        }
    }

    $after = $this->service->enabledKeys((int) $this->company->id);
    sort($after);

    expect($after)->toBe($before);
});

it('never leaves a dashboard widget whose module is off', function () {
    $this->service->disable($this->company->fresh(), 'purchase', cascade: true);

    $widgets = $this->getJson('/api/admin/dashboard')->assertSuccessful()->json('widgets');

    expect($widgets)->not->toContain('purchase_totals', 'recent_bills', 'recent_expenses', 'sales_expense_chart');
});

it('never leaves a report whose module is off', function () {
    $this->service->disable($this->company->fresh(), 'inventory', cascade: true);

    $routes = collect($this->getJson('/api/admin/report-catalogue')->assertSuccessful()->json('data'))
        ->flatMap(fn (array $category): array => array_column($category['items'], 'name'))
        ->all();

    expect($routes)->not->toContain('admin.stock-aging', 'admin.sales-report', 'admin.inventory-valuation');
});
