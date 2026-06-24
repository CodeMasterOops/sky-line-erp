<?php

use App\Services\PermissionRegistry;
use Illuminate\Support\Facades\File;

/**
 * Guards H-3 / M-1 / M-3 (frontend): the Vue router's per-route permission map
 * (resources/js/router/adminRoutePermissions.js) must stay exhaustive and must
 * only reference permissions that actually exist in the backend catalogue.
 * These are read as text so they run inside the normal PHP test suite (CI)
 * without a JS test runner.
 */
function routerMapSource(): string
{
    return File::get(base_path('resources/js/router/adminRoutePermissions.js'));
}

/**
 * @return list<string>
 */
function definedAdminRouteNames(): array
{
    $names = [];

    foreach (File::allFiles(base_path('resources/js/router')) as $file) {
        if ($file->getExtension() !== 'js') {
            continue;
        }

        preg_match_all('/name:\s*[\'"](admin\.[a-z0-9-]+)[\'"]/', $file->getContents(), $matches);
        $names = array_merge($names, $matches[1]);
    }

    return array_values(array_unique($names));
}

/**
 * @return list<string>
 */
function mappedAdminRouteNames(): array
{
    preg_match_all('/[\'"](admin\.[a-z0-9-]+)[\'"]\s*:/', routerMapSource(), $matches);

    return array_values(array_unique($matches[1]));
}

it('maps every admin route name to a permission requirement', function () {
    $defined = definedAdminRouteNames();
    $mapped = mappedAdminRouteNames();

    $missing = array_values(array_diff($defined, $mapped));

    expect($missing)->toBe(
        [],
        'These admin routes have no entry in adminRoutePermissions.js and would be reachable by direct URL without a permission check: '
            .implode(', ', $missing)
    );
});

it('only references permissions that exist in the backend catalogue', function () {
    // Permission *values* are snake_case with no dot; route-name *keys* contain
    // a dot (admin.*). This cleanly separates the two in the same file.
    preg_match_all('/[\'"]([a-z][a-z_]+)[\'"]/', routerMapSource(), $matches);
    $referenced = array_values(array_unique($matches[1]));

    $catalogue = app(PermissionRegistry::class)->all();

    $unknown = array_values(array_diff($referenced, $catalogue));

    expect($unknown)->toBe(
        [],
        'adminRoutePermissions.js references permissions that no controller defines (dead/typo permissions): '
            .implode(', ', $unknown)
    );
});
