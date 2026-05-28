<?php

namespace App\Jobs\DataTransfer;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\DataTransferCompletedNotification;

class NotifyDataTransferCompleteJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public DataTransferJob $dataTransferJob,
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(): void
    {
        $job = $this->dataTransferJob->fresh();
        $user = User::query()->find($job->user_id);

        if ($user) {
            $user->notify(new DataTransferCompletedNotification($job));
        }
    }
}
