<?php

use App\Jobs\DataTransfer\RunScheduledExportJob;
use App\Models\DataTransferSchedule;
use App\Services\Modules\CompanyModuleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data-transfer:prune')->daily();

// Batch expiry
Schedule::command('batch:expire')->dailyAt('01:00');

// Inventory health checks
Schedule::command('inventory:gl-reconcile --limit=500')->dailyAt('03:00');
Schedule::command('inventory:valuation-snapshot --replace')->monthlyOn(1, '01:00');

Schedule::command('app:check-orphan-rows')->dailyAt('02:00');

// Prune expired Sanctum tokens to keep personal_access_tokens lean and lookups fast
Schedule::command('sanctum:prune-expired --hours=720')->daily();

// Remove orphaned product variants left behind after product edits
Schedule::command('products:prune-orphan-variants --apply')->weekly();

// Gym memberships — expire elapsed terms, then remind staff about the ones
// coming up. Both commands skip companies without the gym module.
Schedule::command('gym:process-membership-expiry')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('gym:dispatch-membership-reminders')->dailyAt('08:00')->withoutOverlapping();

// CRM follow-up & task reminders
Schedule::command('crm:dispatch-reminders')->everyFiveMinutes()->withoutOverlapping();

// Scheduled exports — only for companies that still run Data Import / Export.
// A schedule belonging to a company that switched the module off stays exactly
// where it is, inert, and resumes if the module comes back.
Schedule::call(function () {
    $companyIds = app(CompanyModuleService::class)->companyIdsWith('data-transfer');

    if ($companyIds === []) {
        return;
    }

    DataTransferSchedule::query()
        ->withoutGlobalScopes()
        ->whereIn('company_id', $companyIds)
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
        })
        ->each(fn (DataTransferSchedule $schedule) => RunScheduledExportJob::dispatch($schedule));
})->hourly();
