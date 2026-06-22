<?php

use App\Http\Controllers\PosSetupController;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

// Public technician setup page — no auth required.
Route::get('pos-setup', PosSetupController::class)
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->name('pos.setup');

Route::get('{any}', function () {
    return view('app');
})->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->where('any', '^(?!api/|login|logout).*');
