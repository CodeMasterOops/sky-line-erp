<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Setting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SuperAdmin\SupportSettingRequest;

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

    public function store(SupportSettingRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        foreach ($this->supportKeys as $key) {
            $value = $validated[$key] ?? [];
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value)]
            );
        }

        $data = [];
        foreach ($this->supportKeys as $key) {
            $raw = Setting::where('key', $key)->value('value');
            $data[$key] = $raw ? json_decode($raw, true) : [];
        }

        return response()->json([
            'message' => 'Support settings updated successfully',
            'data' => $data,
        ]);
    }
}
