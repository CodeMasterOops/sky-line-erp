<?php

use App\Jobs\DataTransfer\RunScheduledExportJob;
use App\Models\DataTransferSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data-transfer:prune')->daily();

// Inventory health checks
Schedule::command('inventory:gl-reconcile --limit=500')->dailyAt('03:00');
Schedule::command('inventory:valuation-snapshot --replace')->monthlyOn(1, '01:00');

Schedule::command('app:check-orphan-rows')->dailyAt('02:00');

Schedule::call(function () {
    DataTransferSchedule::query()
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
        })
        ->each(fn (DataTransferSchedule $schedule) => RunScheduledExportJob::dispatch($schedule));
})->hourly();
