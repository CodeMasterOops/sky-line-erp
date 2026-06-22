<?php

use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\BarcodeService;

it('uses landscape orientation for roll paper', function () {
    $captured = [];

    Pdf::shouldReceive('loadView')
        ->once()
        ->andReturnSelf();

    Pdf::shouldReceive('setPaper')
        ->once()
        ->withArgs(function ($size, $orientation) use (&$captured) {
            $captured['size'] = $size;
            $captured['orientation'] = $orientation;

            return true;
        })
        ->andReturnSelf();

    Pdf::shouldReceive('setOptions')->once()->andReturnSelf();

    (new BarcodeService)->generatePdf([], 'roll');

    expect($captured['orientation'])->toBe('landscape')
        ->and($captured['size'])->toBe([0, 0, 144, 72]);
});

it('uses portrait orientation for a4 paper', function () {
    $captured = [];

    Pdf::shouldReceive('loadView')->once()->andReturnSelf();

    Pdf::shouldReceive('setPaper')
        ->once()
        ->withArgs(function ($size, $orientation) use (&$captured) {
            $captured['orientation'] = $orientation;

            return true;
        })
        ->andReturnSelf();

    Pdf::shouldReceive('setOptions')->once()->andReturnSelf();

    (new BarcodeService)->generatePdf([], 'a4');

    expect($captured['orientation'])->toBe('portrait');
});

it('uses portrait orientation for letter paper', function () {
    $captured = [];

    Pdf::shouldReceive('loadView')->once()->andReturnSelf();

    Pdf::shouldReceive('setPaper')
        ->once()
        ->withArgs(function ($size, $orientation) use (&$captured) {
            $captured['orientation'] = $orientation;

            return true;
        })
        ->andReturnSelf();

    Pdf::shouldReceive('setOptions')->once()->andReturnSelf();

    (new BarcodeService)->generatePdf([], 'letter');

    expect($captured['orientation'])->toBe('portrait');
});
