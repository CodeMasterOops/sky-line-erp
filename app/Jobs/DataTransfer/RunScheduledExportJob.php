<?php

namespace App\Jobs\DataTransfer;

use App\Models\User;
use Illuminate\Bus\Queueable;
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

        $entityType = DataTransferEntityTypeEnum::from($schedule->entity_type);

        $job = $uploadService->createExportJob(
            $user,
            $entityType,
            $schedule->format,
            $schedule->filters ?? [],
        );

        $schedule->update(['last_run_at' => now()]);

        GenerateExportJob::dispatch($job);
    }
}
