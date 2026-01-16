<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

Route::get('{any}', function () {
    return view('app');
})->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, VerifyCsrfToken::class])
    ->where('any', '^(?!api/|login|logout).*');
