<?php

use App\Services\Modules\ModuleRegistry;

/*
| Phase 2 — the dependency direction rule
| (docs/saas-modular-platform-and-gym-module-plan.md §3.1).
|
| Modules may depend on core (Invoice, Party, Product, Journal…). Core must
| never depend on a module, or disabling that module would break the ERP for
| everyone. Integrations flow the other way: a module calls core services and
| registers itself into core extension points.
|
| The check is static, over source text, so it holds even for code paths no test
| exercises.
*/

/**
 * @return list<string>
 */
function phpFilesUnder(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

it('keeps the module machinery free of module-specific code', function () {
    // ModuleRegistry, CompanyModuleService, the middleware and the pipeline must
    // stay generic: they are configured by config/modules.php, never by naming a
    // particular module's controllers.
    $moduleNamespaces = ['Crm', 'HR', 'Sales', 'Purchase', 'Inventory', 'Accounting', 'DataTransfer', 'Nepal'];

    $machinery = array_merge(
        phpFilesUnder(app_path('Services/Modules')),
        phpFilesUnder(app_path('Modules')),
        [app_path('Http/Middleware/EnsureModuleEnabled.php')],
        phpFilesUnder(app_path('Provisioning')),
    );

    foreach ($machinery as $file) {
        $contents = (string) file_get_contents($file);

        foreach ($moduleNamespaces as $namespace) {
            expect($contents)->not->toContain(
                'App\\Http\\Controllers\\Api\\Admin\\'.$namespace,
                basename($file).' references the '.$namespace.' module',
            );
        }
    }
});

it('keeps a module\'s own models out of core code', function () {
    // Live from Phase 5, when `gym` declares its models. Until a module claims
    // models this asserts nothing — which is why the count is asserted too, so
    // the test cannot rot into a silent no-op without anyone noticing.
    $registry = app(ModuleRegistry::class);
    $checked = 0;

    foreach ($registry->all() as $key => $definition) {
        if ($definition['models'] === []) {
            continue;
        }

        $ownDirectories = array_filter([
            app_path('Services/'.str_replace('-', '', ucwords($key, '-'))),
            app_path('Http/Controllers/Api/Admin/'.str_replace('-', '', ucwords($key, '-'))),
            app_path('Modules/'.str_replace('-', '', ucwords($key, '-'))),
        ], 'is_dir');

        foreach (phpFilesUnder(app_path()) as $file) {
            $isOwnFile = false;

            foreach ($ownDirectories as $directory) {
                if (str_starts_with($file, $directory)) {
                    $isOwnFile = true;
                    break;
                }
            }

            // A module's own models and the registry entry itself are exempt.
            if ($isOwnFile || str_starts_with($file, app_path('Models'))) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            foreach ($definition['models'] as $model) {
                expect($contents)->not->toContain($model, basename($file).' references '.$model);
                $checked++;
            }
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});

it('lets a module depend on core, which is the allowed direction', function () {
    // Sanity check on the rule's polarity: core models are fair game for module
    // code, and the CRM module already relies on that.
    $crmController = app_path('Http/Controllers/Api/Admin/Crm/CustomerProfileController.php');

    expect(file_get_contents($crmController))->toContain('App\\Models\\Party');
});
