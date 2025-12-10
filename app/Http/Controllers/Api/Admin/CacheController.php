<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

class CacheController extends Controller
{
    /**
     * @Permissions("clear_system_cache", group="cache", desc="Clear System Cache")
     */
    public function clearCache()
    {
        Artisan::call('cache:clear');

        return response()->json([
            'message' => 'Cache cleared successfully',
        ]);
    }

    /**
     * @Permissions("clear_cloudflare_api_cache", group="cache", desc="Clear Api Cache")
     */
    public function clearCloudflareApiCache()
    {
        $response = flushCloudflarePrefixCache(['api']);

        if ($response) {
            return response()->json([
                'message' => 'Cache purged successfully',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to clear cache.',
            ], 400);
        }
    }

    /**
     * @Permissions("clear_cloudflare_cache", group="cache", desc="Clear Cloudflare Cache")
     */
    public function clearCloudflareCache(Request $request)
    {
        $cleanedLinks = str_replace(["\n", "\r"], ',', rtrim($request->input('links'), ','));

        $validUrls = array_map('trim', array_values(array_filter(explode(',', $cleanedLinks))));

        $validator = Validator::make(
            ['links' => $validUrls],
            ['links.*' => ['required', 'url']]
        );
        if ($validator->fails()) {
            return response()->json([
                'message' => 'One or more URLs are not valid.',
            ], 400);
        }

        $response = flushCloudflareCache($validUrls);
        if ($response) {
            return response()->json([
                'message' => 'Cache cleared successfully',
            ]);
        } else {
            return response()->json([
                'message' => 'Failed to clear cache.',
            ], 400);
        }
    }
}
