<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\FaqController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\BlogController;
use App\Http\Controllers\Api\Admin\EnumController;
use App\Http\Controllers\Api\Admin\FileController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\PageController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\CacheController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\StockController;
use App\Http\Controllers\Api\Admin\AuthorController;
use App\Http\Controllers\Api\Admin\BannerController;
use App\Http\Controllers\Api\Admin\CouponController;
use App\Http\Controllers\Api\Admin\FolderController;
use App\Http\Controllers\Api\Admin\VendorController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Admin\AttributeController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CollectionController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\SubscriberController;
use App\Http\Controllers\Api\Admin\AnnouncementController;
use App\Http\Controllers\Api\Admin\BlogCategoryController;
use App\Http\Controllers\Api\Admin\ProductCategoryController;
use App\Http\Controllers\Api\Admin\ShippingSettingController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('logout', 'logout')->middleware('auth:admin');
});

Route::middleware('auth:admin')->group(function () {
    Route::middleware('checkRole')->group(function () {
        // profile
        Route::prefix('profile')->as('profile')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'profile')->name('index');
            Route::post('update', 'updateProfile')->name('update');
            Route::put('change-password', 'changePassword')->name('changePassword');
        });

        // dashboard
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::apiResource('setting', SettingController::class)->only('index', 'store');

        // user management
        Route::get('permission', PermissionController::class)->name('permissions');
        Route::apiResource('role', RoleController::class);
        Route::put('user/{user}/update-status', [UserController::class, 'updateStatus'])->name('user.update-status');
        Route::apiResource('user', UserController::class);

        // notifications
        Route::prefix('notification')->as('notification.')->controller(AdminNotificationController::class)->group(function () {
            Route::get('all', 'allNotifications')->name('all');
            Route::get('unread', 'unreadNotifications')->name('unread');
            Route::post('mark-as-read/{id?}', 'markAsRead')->name('mark-as-read');
        });

        // vendor
        Route::put('vendor/{vendor}/update-status', [VendorController::class, 'updateStatus'])->name('vendor.update-status');
        Route::put('vendor/{vendor}/reset-password', [VendorController::class, 'resetPassword'])->name('vendor.reset-password');
        Route::post('vendor/{vendor}/login', [VendorController::class, 'vendorLogin'])->name('vendor.login');
        Route::post('vendor/{vendor}/verify', [VendorController::class, 'verifyVendor'])->name('vendor.verify');
        Route::post('vendor/{vendor}/reject', [VendorController::class, 'rejectVendor'])->name('vendor.reject');
        Route::apiResource('vendor', VendorController::class);

        // customer
        Route::put('customer/{customer}/update-status', [CustomerController::class, 'updateStatus'])->name('customer.update-status');
        Route::apiResource('customer', CustomerController::class)->only('index', 'show', 'destroy');

        // file manager
        Route::apiResource('folder', FolderController::class);
        Route::get('file/trashed/list', [FileController::class, 'trashed'])->name('file.trashed.list');
        Route::post('file/{id}/restore', [FileController::class, 'restore'])->name('file.trashed.restore');
        Route::delete('file/{id}/delete-permanently', [FileController::class, 'deletePermanently'])->name('file.delete-permanently');
        Route::apiResource('file', FileController::class);

        // banner
        Route::put('banner/{banner}/update-status', [BannerController::class, 'updateStatus'])->name('banner.update-status');
        Route::apiResource('banner', BannerController::class);

        // page
        Route::put('page/{page}/update-status', [PageController::class, 'updateStatus'])->name('page.update-status');
        Route::apiResource('page', PageController::class);

        // faq
        Route::put('faq/{faq}/update-status', [FaqController::class, 'updateStatus'])->name('faq.update-status');
        Route::apiResource('faq', FaqController::class);

        // author
        Route::put('author/{author}/update-status', [AuthorController::class, 'updateStatus'])->name('author.update-status');
        Route::apiResource('author', AuthorController::class);

        // tag
        Route::apiResource('tag', TagController::class);

        // blog category
        Route::put('blog-category/{blog_category}/update-status', [BlogCategoryController::class, 'updateStatus'])->name('blog-category.update-status');
        Route::apiResource('blog-category', BlogCategoryController::class);

        // blog
        Route::put('blog/{blog}/update-status', [BlogController::class, 'updateStatus'])->name('blog.update-status');
        Route::apiResource('blog', BlogController::class);

        // announcement
        Route::put('announcement/{announcement}/update-status', [AnnouncementController::class, 'updateStatus'])->name('announcement.update-status');
        Route::apiResource('announcement', AnnouncementController::class);

        // menu
        Route::put('menu/{menu}/update-status', [MenuController::class, 'updateStatus'])->name('menu.update-status');
        Route::put('menu/{menu}/update-sort-order', [MenuController::class, 'updateSortOrder'])->name('menu.update-sort-order');
        Route::apiResource('menu', MenuController::class);

        // cache
        Route::prefix('cache')->as('cache.')->controller(CacheController::class)->group(function () {
            Route::post('clear-system-cache', 'clearCache')->name('clear-system-cache');
            Route::post('clear-cloudflare-api-cache', 'clearCloudflareApiCache')->name('clear-api-cache');
            Route::post('clear-cloudflare-cache', 'clearCloudflareCache')->name('clear-cloudflare-cache');
        });

        // product category
        Route::put('product-category/{product_category}/update-status', [ProductCategoryController::class, 'updateStatus'])->name('product-category.update-status');
        Route::put('product-category/{product_category}/update-featured-status', [ProductCategoryController::class, 'updateFeaturedStatus'])->name('product-category.update-featured-status');
        Route::apiResource('product-category', ProductCategoryController::class);

        // brand
        Route::put('brand/{brand}/update-status', [BrandController::class, 'updateStatus'])->name('brand.update-status');
        Route::put('brand/{brand}/update-featured-status', [BrandController::class, 'updateFeaturedStatus'])->name('brand.update-featured-status');
        Route::apiResource('brand', BrandController::class);

        // product attribute
        Route::apiResource('attribute', AttributeController::class);

        // product
        Route::get('product/list/all', [ProductController::class, 'allProducts'])->name('product.list.all');
        Route::put('product/{product}/update-status', [ProductController::class, 'updateStatus'])->name('product.update-status');
        Route::apiResource('product', ProductController::class);

        // collection
        Route::put('collection/{collection}/update-status', [CollectionController::class, 'updateStatus'])->name('collection.update-status');
        Route::apiResource('collection', CollectionController::class);

        // orders
        Route::put('order/{order}/update-status', [OrderController::class, 'updateStatus'])->name('order.update-status');
        Route::post('order/{order}/shipping-label', [OrderController::class, 'shippingLabel'])->name('order.shipping-label');
        Route::apiResource('order', OrderController::class)->only('index', 'show', 'destroy');

        // stock
        Route::prefix('stock')->as('stock.')->controller(StockController::class)->group(function () {
            Route::get('list', 'index')->name('list');
            Route::post('{product_variant}/update', 'updateStock')->name('update');
            Route::get('{product_variant}/history', 'stockHistory')->name('history');
        });

        // marketing

        // subscriber
        Route::apiResource('subscriber', SubscriberController::class)->only('index', 'destroy');

        // shipping setting
        Route::apiResource('shipping-setting', ShippingSettingController::class)->only('index', 'store');

        // coupon
        Route::put('coupon/{coupon}/update-status', [CouponController::class, 'updateStatus'])->name('coupon.update-status');
        Route::apiResource('coupon', CouponController::class);
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
