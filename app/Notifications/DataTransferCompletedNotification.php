<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use Illuminate\Notifications\Notification;

class DataTransferCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public DataTransferJob $dataTransferJob,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $job = $this->dataTransferJob;
        $stats = $job->stats ?? [];

        return [
            'type' => 'data_transfer_completed',
            'job_uuid' => $job->uuid,
            'direction' => $job->direction->value,
            'entity_type' => $job->entity_type->value,
            'status' => $job->status->value,
            'message' => sprintf(
                '%s %s finished (%s).',
                ucfirst($job->direction->value),
                str_replace('_', ' ', $job->entity_type->value),
                $job->status->value,
            ),
            'stats' => $stats,
        ];
    }
}
