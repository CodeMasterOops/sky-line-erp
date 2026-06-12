<?php

use Carbon\Carbon;
use App\NepaliDateConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

if (! function_exists('allTables')) {
    function allTables()
    {
        return Cache::rememberForever('allTables', function () {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $list = [];
                foreach (Schema::getTableListing() as $table) {
                    $list[$table] = Schema::getColumnListing($table);
                }

                return $list;
            }

            $tables = DB::select('SHOW TABLES');
            $list = [];
            foreach ($tables as $table) {
                foreach ($table as $key => $value) {
                    $list[$value] = Schema::getColumnListing($value);
                }
            }

            return $list;
        });
    }
}

if (! function_exists('tableColumns')) {
    function tableColumns($table)
    {
        // Tolerate the SQLite schema-qualified key (`main.<table>`) returned by
        // Schema::getTableListing() in Laravel 12. Without this, columnExists()
        // returns false in SQLite tests for every table, so the MultiTenant /
        // BranchTenant global scopes never register in the test harness.
        $all = allTables();

        return $all[$table] ?? $all['main.'.$table] ?? [];
    }
}

if (! function_exists('columnExists')) {
    function columnExists($table, $column): bool
    {
        return in_array($column, tableColumns($table));
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
    function setting($column = null)
    {
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
