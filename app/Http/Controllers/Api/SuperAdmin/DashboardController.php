<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'users_count' => 0,
        ]);
    }
}
