<?php

use Illuminate\Support\Facades\URL;
use App\Providers\AppServiceProvider;

test('production forces https asset urls when app url is http', function () {
    app()->detectEnvironment(fn () => 'production');

    config(['app.url' => 'http://app.skyerppro.com']);

    (new AppServiceProvider(app()))->boot();

    expect(asset('favicon.ico'))->toBe('https://app.skyerppro.com/favicon.ico');
});

test('force https env overrides non-production environment', function () {
    config([
        'app.env' => 'staging',
        'app.url' => 'http://app.skyerppro.com',
    ]);

    putenv('FORCE_HTTPS=true');

    (new AppServiceProvider(app()))->boot();

    expect(URL::formatScheme())->toBe('https://');

    putenv('FORCE_HTTPS');
});
