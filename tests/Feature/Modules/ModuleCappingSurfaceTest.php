<?php

use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\Route;
use App\Services\Modules\ModuleRegistry;
use App\Services\Reports\ReportCatalogue;

/*
| Phase 2 — the surface inventory
| (docs/module-capping-and-advanced-handling-plan.md Phase 8.3, pulled forward).
|
| Every capping surface is a tagged catalogue, and a tag that names a module the
| registry has never heard of caps nothing at all — it just sits there looking
| correct. These assertions are what stop a typo, or a module rename, from
| quietly un-capping a surface.
*/

it('tags every report with a module the registry knows', function () {
    $registry = app(ModuleRegistry::class);

    $unknown = collect(app(ReportCatalogue::class)->allItems())
        ->filter(fn (array $item): bool => $item['module'] !== null && ! $registry->has($item['module']))
        ->map(fn (array $item): string => "{$item['route']} => {$item['module']}")
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('guards every report with a permission the application enforces', function () {
    $enforced = app(PermissionRegistry::class)->all();

    $unknown = collect(app(ReportCatalogue::class)->allItems())
        ->filter(fn (array $item): bool => $item['permission'] !== null && ! in_array($item['permission'], $enforced, true))
        ->map(fn (array $item): string => "{$item['route']} => {$item['permission']}")
        ->values()
        ->all();

    expect($unknown)->toBe([]);
});

it('gives every report a label and a route', function () {
    foreach (app(ReportCatalogue::class)->allItems() as $item) {
        expect($item['label'])->not->toBe('')
            ->and($item['route'])->toStartWith('admin.');
    }
});

it('names every dashboard widget module in the registry', function () {
    $registry = app(ModuleRegistry::class);

    $widgets = (new ReflectionClass(\App\Http\Controllers\Api\Admin\DashboardController::class))
        ->getConstant('WIDGETS');

    foreach ($widgets as $widget => $moduleKeys) {
        foreach ($moduleKeys as $moduleKey) {
            expect($registry->has($moduleKey))
                ->toBeTrue("Dashboard widget [{$widget}] names unknown module [{$moduleKey}].");
        }
    }
});

it('tags every settings navigation entry with a module the registry knows', function () {
    $registry = app(ModuleRegistry::class);
    $sections = json_decode(file_get_contents(resource_path('js/assets/json/settings.json')), true);

    foreach ($sections as $section) {
        $keys = array_filter([
            $section['module'] ?? null,
            ...array_map(fn (array $item): ?string => $item['module'] ?? null, $section['subMenu'] ?? []),
        ]);

        foreach ($keys as $moduleKey) {
            expect($registry->has($moduleKey))
                ->toBeTrue("Settings navigation names unknown module [{$moduleKey}].");
        }
    }
});

it('exposes the module catalogue the SPA reads its labels from', function () {
    // ModuleUnavailable.vue used to carry a hand-copied label map that went
    // stale every time a module shipped.
    $keys = collect(app(ModuleRegistry::class)->all())->keys();

    expect(Route::has('api.admin.module.catalogue'))->toBeTrue()
        ->and($keys)->toContain('core', 'gym');
});
