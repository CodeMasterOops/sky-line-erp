<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Account;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\AccountGroup;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'customers_count' => 0,
            'account_groups_count' => AccountGroup::count(),
            'accounts_count' => Account::count(),
            'products_count' => Product::count(),
            'warehouse_count' => Warehouse::count(),
            'users_count' => User::count(),
        ]);
    }
}
