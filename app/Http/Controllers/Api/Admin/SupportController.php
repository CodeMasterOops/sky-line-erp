<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Setting;
use App\Http\Controllers\Controller;

class SupportController extends Controller
{
    private array $supportKeys = [
        'support_phones',
        'support_emails',
        'support_whatsapp',
        'support_social_links',
        'support_videos',
    ];

    public function index(): \Illuminate\Http\JsonResponse
    {
        $data = [];
        foreach ($this->supportKeys as $key) {
            $raw = Setting::where('key', $key)->value('value');
            $data[$key] = $raw ? json_decode($raw, true) : [];
        }

        return response()->json(['data' => $data]);
    }
}
