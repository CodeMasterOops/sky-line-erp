<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

class EnumController extends Controller
{
    public function orderStatus()
    {
        $orderStatus = OrderStatusEnum::orderStatusList();

        return response()->json([
            'data' => $orderStatus,
        ]);
    }
}
