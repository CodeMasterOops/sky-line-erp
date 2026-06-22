<?php

namespace App\Http\Controllers;

use App\Services\Pos\QzTrayService;

class PosSetupController extends Controller
{
    public function __construct(protected QzTrayService $qzTray) {}

    public function __invoke(): \Illuminate\View\View
    {
        return view('pos-setup', [
            'certificate' => $this->qzTray->certificate(),
            'configured' => $this->qzTray->isConfigured(),
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
        ]);
    }
}
