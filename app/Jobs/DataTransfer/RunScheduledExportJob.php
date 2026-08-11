<?php

namespace App\Jobs\DataTransfer;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Services\TenantService;
use App\Models\DataTransferSchedule;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\DataTransfer\UploadService;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;

class RunScheduledExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public DataTransferSchedule $schedule,
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(UploadService $uploadService): void
    {
        $schedule = $this->schedule->fresh();

        if (! $schedule?->is_active) {
            return;
        }

        $user = User::query()->find($schedule->user_id);
        if (! $user) {
            return;
        }

        // A schedule outlives a module change. `routes/console.php` already
        // skips companies without the module, but a schedule can be dispatched
        // from elsewhere, so the job is the one that must not produce and
        // deliver an export for a module the company no longer runs.
        if (! moduleEnabled('data-transfer', (int) $user->company_id)) {
            return;
        }

        TenantService::setCompanyId($user->company_id);

        try {
            $entityType = DataTransferEntityTypeEnum::from($schedule->entity_type);

            $job = $uploadService->createExportJob(
                $user,
                $entityType,
                $schedule->format,
                $schedule->filters ?? [],
            );

            $schedule->update(['last_run_at' => now()]);

            GenerateExportJob::dispatch($job);
        } finally {
            TenantService::reset();
        }
    }
}
