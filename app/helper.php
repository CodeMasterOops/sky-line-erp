<?php

use App\Models\File;
use App\Models\Folder;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

if (! function_exists('allTables')) {
    function allTables()
    {
        return Cache::rememberForever('allTables', function () {
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
        return allTables()[$table] ?? [];
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
        $user = auth()->user();

        if ($user->user_type == \App\Enums\UserTypeEnum::ADMIN) {
            return true;
        }

        $permissionToCheck = is_array($permissions) ? $permissions : [$permissions];

        foreach ($permissionToCheck as $p) {
            if (in_array($p, userPermissions($user))) {
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
                if (in_array($d->key, ['logo', 'favicon', 'og_image'])) {
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

if (! function_exists('uploadFile')) {
    function uploadFile($file, $folder)
    {
        $path = null;

        if ($folder instanceof Folder) {
            $pathList = [];
            $f = $folder;

            while ($f) {
                array_unshift($pathList, $f->slug);
                $f = $f->parent;
            }

            $path = implode('/', $pathList);
        } elseif (is_string($folder)) {
            $path = $folder;
        }

        $extension = $file->extension();
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $formattedFileName = strtolower(preg_replace('/\s+/', '-', $fileName));
        $generatedFileName = sprintf('%s.%s', $formattedFileName, $extension);

        $filePath = $path.'/'.$generatedFileName;
        $filePathCount = File::where('path', $filePath)->withTrashed()->count();
        if ($filePathCount > 0) {
            $formattedFileName = $formattedFileName.'_'.Str::random(5);
            $generatedFileName = sprintf('%s.%s', $formattedFileName, $extension);
        }

        return $path ? Storage::putFileAs($path, $file, $generatedFileName) : null;
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

if (! function_exists('truncateSentence')) {
    function truncateSentence($text, $limit, $endingDelimiter = '...')
    {
        $words = explode(' ', strip_tags($text));
        $output = implode(' ', array_slice($words, 0, $limit));

        if ($output !== $text) {
            $output .= $endingDelimiter;
        }

        return trim($output);
    }
}

if (! function_exists('isUrl')) {
    function isUrl($string): bool
    {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }
}

if (! function_exists('flushCloudflareCache')) {
    function flushCloudflareCache(array $urls): bool
    {
        if (! config('app.cache_enabled')) {
            return false;
        }

        $client = new Client;

        $files = [];

        foreach ($urls as $url) {
            $files[] = $url;
            $files[] = [
                'url' => $url,
                'headers' => [
                    'Origin' => config('app.front_url'),
                    'Accept-Language' => config('app.locale'),
                ],
            ];
        }

        try {
            $response = $client->post(sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache',
                config('app.cloudflare.zone_id')), [
                    'timeout' => 10,
                    'headers' => [
                        'X-Auth-Email' => config('app.cloudflare.x_auth_email'),
                        'X-Auth-Key' => config('app.cloudflare.x_auth_key'),
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'files' => $files,
                    ]),
                ]);

            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (\Throwable $t) {
            return false;
        }

        return false;
    }
}
if (! function_exists('flushCloudflarePrefixCache')) {
    function flushCloudflarePrefixCache(array $prefixes): bool
    {
        if (! config('app.cache_enabled')) {
            return false;
        }

        $client = new Client;

        $prefixList = [];

        foreach ($prefixes as $prefix) {
            $prefixList[] = config('app.app_domain').'/'.$prefix;
        }

        try {
            $response = $client->post(sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache',
                config('app.cloudflare.zone_id')), [
                    'timeout' => 10,
                    'headers' => [
                        'X-Auth-Email' => config('app.cloudflare.x_auth_email'),
                        'X-Auth-Key' => config('app.cloudflare.x_auth_key'),
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'prefixes' => $prefixList,
                    ]),
                ]);

            if ($response->getStatusCode() === 200) {
                return true;
            }
        } catch (\Throwable $t) {
            return false;
        }

        return false;
    }
}

if (! function_exists('archiveFile')) {
    function archiveFile($file)
    {
        return Str::replaceFirst('/media/files/', '', $file);
    }
}

if (! function_exists('isEuropeanUnion')) {
    function isEuropeanUnion($country): bool
    {
        $countries = [
            'AT', // Austria
            'BE', // Belgium
            'BG', // Bulgaria
            'HR', // Croatia
            'CZ', // Czech Republic
            'DK', // Denmark
            'EE', // Estonia
            'FI', // Finland
            'FR', // France
            'DE', // Germany
            'GR', // Greece
            'HU', // Hungary
            'IE', // Ireland
            'IT', // Italy
            'LV', // Latvia
            'LT', // Lithuania
            'LU', // Luxembourg
            'MT', // Malta
            'NL', // Netherlands
            'PL', // Poland
            'PT', // Portugal
            'RO', // Romania
            'SK', // Slovakia
            'SI', // Slovenia
            'ES', // Spain
            'SE', // Sweden
            'CY', // Republic of Cyprus
        ];

        if (in_array($country, $countries)) {
            return true;
        }

        return false;
    }
}
