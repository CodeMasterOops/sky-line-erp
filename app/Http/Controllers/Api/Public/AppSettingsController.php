<?php

namespace App\Http\Controllers\Api\Public;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class AppSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'registration_enabled' => config('app.registration_enabled'),
            'support_email' => config('app.support_email'),
        ]);
    }
}
