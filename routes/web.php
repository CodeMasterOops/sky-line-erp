<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('{any}', function () {
    return view('app');
})->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->where('any', '^(?!api/|login|logout).*');
