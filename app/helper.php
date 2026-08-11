<?php

use Carbon\Carbon;
use App\NepaliDateConverter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

if (! function_exists('allTablesCacheKey')) {
    function allTablesCacheKey(): string
    {
        $hash = config('app.version', env('DEPLOY_HASH', 'default'));

        return 'allTables:'.$hash;
    }
}

if (! function_exists('allTables')) {
    function allTables()
    {
        $cached = Cache::get(allTablesCacheKey());

        // Don't trust a cached empty result — the database may not have been
        // ready when the cache was first written (e.g. during service-provider
        // boot before RefreshDatabase restores the in-memory PDO in tests).
        if (is_array($cached) && count($cached) > 0) {
            return $cached;
        }

        // Use the Laravel cross-DB API instead of MySQL-specific 'SHOW TABLES'.
        // Schema::getTableListing() works on MySQL, PostgreSQL, and SQLite alike.
        // SQLite in Laravel 12 returns schema-qualified names like 'main.users';
        // Schema::getColumnListing() requires the plain name, so strip the prefix.
        //
        // On MySQL shared servers, Schema::getTableListing() may return schema-
        // qualified names for ALL databases the user can see ('dbname.tablename').
        // We filter to only the current database and store plain table names so
        // columnExists() works regardless of how many databases the server hosts.
        $connection = config('database.default');
        $currentDb = config("database.connections.{$connection}.database");

        $list = [];
        foreach (Schema::getTableListing() as $table) {
            $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;

            if ($currentDb && str_contains($plainName, '.')) {
                [$schema, $name] = explode('.', $plainName, 2);
                if ($schema !== $currentDb) {
                    continue;
                }
                $plainName = $name;
            }

            $list[$plainName] = Schema::getColumnListing($plainName);
        }

        if (! empty($list)) {
            Cache::forever(allTablesCacheKey(), $list);
        }

        return $list;
    }
}

if (! function_exists('tableColumns')) {
    function tableColumns($table)
    {
        $all = allTables();

        if (isset($all[$table])) {
            return $all[$table];
        }

        // SQLite in Laravel 12: 'main.tablename' — tolerate old cache format.
        if (isset($all['main.'.$table])) {
            return $all['main.'.$table];
        }

        // MySQL on shared servers may have cached schema-qualified keys
        // ('dbname.tablename') before this fix was applied. Tolerate old cache.
        $connection = config('database.default');
        $currentDb = config("database.connections.{$connection}.database");
        if ($currentDb && isset($all[$currentDb.'.'.$table])) {
            return $all[$currentDb.'.'.$table];
        }

        return [];
    }
}

if (! function_exists('columnExists')) {
    function columnExists($table, $column): bool
    {
        return in_array($column, tableColumns($table));
    }
}

if (! function_exists('warehouseBranchId')) {
    /**
     * Resolve a warehouse's owning branch_id. Used so warehouse-keyed inventory
     * records (stock, layers, batches, movements) always belong to their
     * warehouse's branch — including the destination side of a cross-branch
     * stock transfer. Not statically cached: a process-level cache would leak
     * stale mappings across requests/tests (cf. TenantService reset).
     */
    function warehouseBranchId(int $warehouseId): ?int
    {
        $branchId = \App\Models\Warehouse::withoutGlobalScopes()
            ->whereKey($warehouseId)
            ->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }
}

if (! function_exists('moduleEnabled')) {
    /**
     * Whether the given module is switched on for a company — the current
     * tenant unless one is named. Use it to hide a module's own surfaces
     * (menu entries, dashboard widgets, report sources, notifications);
     * route access is already gated by the `module` middleware.
     */
    function moduleEnabled(string $moduleKey, ?int $companyId = null): bool
    {
        if ($companyId !== null) {
            return app(\App\Services\Modules\CompanyModuleService::class)
                ->isEnabled($moduleKey, $companyId);
        }

        return app(\App\Services\Modules\ModuleGate::class)->enabled($moduleKey);
    }
}

if (! function_exists('hasPermission')) {
    function hasPermission($permissions): bool
    {
        $user = auth('admin')->user();

        if ($user->user_type == \App\Enums\UserTypeEnum::ADMIN) {
            return true;
        }

        // When inside a branch context, use branch-specific permissions stored
        // by SetTenantContext rather than re-querying the user's company roles.
        // The wildcard '*' value means the effective role grants all permissions.
        $branchPermissions = \App\Services\TenantService::branchPermissions();
        $activePermissions = $branchPermissions ?? userPermissions($user);

        if (in_array('*', $activePermissions)) {
            return true;
        }

        $permissionToCheck = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissionToCheck as $p) {
            if (in_array($p, $activePermissions)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('userPermissions')) {
    function userPermissions($user): array
    {
        $user->load('roles');

        $userPermissions = [];

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $userPermissions[] = $permission;
            }
        }

        return $userPermissions;
    }
}

if (! function_exists('setting')) {
    /**
     * Global-only system settings (no company_id column on the settings table).
     * Must only be called from SuperAdmin context. Calling from a tenant/admin
     * context is a contract violation — logged as a warning so it can be caught
     * and fixed.
     */
    function setting($column = null)
    {
        if (\App\Services\TenantService::companyId() !== null) {
            \Illuminate\Support\Facades\Log::warning('[SETTING] setting() called from tenant context — global-only contract violated', [
                'company_id' => \App\Services\TenantService::companyId(),
                'path' => request()->path(),
            ]);
        }

        $setting = Cache::rememberForever('setting', function () {
            $settingData = \App\Models\Setting::all();
            $data = new stdClass;

            foreach ($settingData as $d) {
                $data->{$d->key} = $d->value;
                if (in_array($d->key, ['logo'])) {
                    $data->{$d->key.'_url'} = $d->value ? Storage::url($d->value) : '';
                }
            }

            return $data;
        });

        if ($column) {
            return $setting->{$column} ?? null;
        }

        return $setting;
    }
}

if (! function_exists('deleteFile')) {
    function deleteFile($path)
    {
        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }
}

if (! function_exists('limitParagraph')) {
    function limitParagraphs($text, $limit)
    {
        $pattern = '/<p\b[^>]*>(.*?)<\/p>/i';

        preg_match_all($pattern, $text, $matches);

        $extractedParagraphs = array_slice($matches[0], 0, $limit);

        return implode('', $extractedParagraphs);
    }
}

if (! function_exists('isUrl')) {
    function isUrl($string): bool
    {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }
}

if (! function_exists('convertToNepali')) {
    function convertToNepali($number): string
    {
        $devanagariDigits = [
            '0' => '०', '1' => '१', '2' => '२', '3' => '३',
            '4' => '४', '5' => '५', '6' => '६', '7' => '७',
            '8' => '८', '9' => '९',
        ];

        return strtr($number, $devanagariDigits);
    }
}

if (! function_exists('adToBsDate')) {
    function adToBsDate($adDate, $format = 'en', $separator = '-'): string
    {
        $nepaliDate = (new NepaliDateConverter)->convertDateToNepali(Carbon::parse($adDate)->format('Y-m-d'));

        $convertedDate = $nepaliDate['year'].$separator.Str::padLeft($nepaliDate['month'], 2, 0).$separator.Str::padLeft($nepaliDate['date'], 2, 0);

        if ($format == 'np') {
            return convertToNepali($convertedDate);
        }

        return $convertedDate;
    }
}

if (! function_exists('sanitizeDownloadFilename')) {
    function sanitizeDownloadFilename(string $filename): string
    {
        return str_replace(['/', '\\'], '-', $filename);
    }
}

if (! function_exists('format_money')) {
    function format_money(float|int|string|null $amount, ?string $symbol = null): string
    {
        if ($amount === null || $amount === '') {
            return '—';
        }

        $numeric = is_numeric($amount) ? (float) $amount : null;

        if ($numeric === null) {
            return '—';
        }

        $symbol ??= config('currency.symbol', 'Rs.');

        return $symbol.' '.number_format($numeric, 2);
    }
}

if (! function_exists('adToBsDateTime')) {
    function adToBsDateTime($adDate, $format = 'en'): string
    {
        $nepaliDate = (new NepaliDateConverter)->convertDateToNepali(Carbon::parse($adDate)->format('Y-m-d'));

        $convertedDate = $nepaliDate['year'].'-'.Str::padLeft($nepaliDate['month'], 2, 0).'-'.Str::padLeft($nepaliDate['date'], 2, 0);

        $convertedDateTime = $convertedDate.', '.Carbon::parse($adDate)->format('g:i A');

        if ($format == 'np') {
            return convertToNepali($convertedDateTime);
        }

        return $convertedDateTime;
    }
}
