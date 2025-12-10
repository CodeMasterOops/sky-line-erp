<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Blog;
use App\Models\File;
use App\Models\User;
use App\Models\Brand;
use App\Models\Banner;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\Customer;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'users_count' => User::count(),
            'banners_count' => Banner::count(),
            'media_count' => File::count(),
            'vendors_count' => Vendor::count(),
            'customers_count' => Customer::count(),
            'blogs_count' => Blog::count(),
            'brands_count' => Brand::count(),
            'products_count' => Product::count(),
        ]);
    }
}
