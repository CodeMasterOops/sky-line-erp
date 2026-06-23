<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class CrmReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Model $reminder,
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
        return $this->reminder instanceof FollowUp
            ? $this->followUpPayload($this->reminder)
            : $this->taskPayload($this->reminder);
    }

    /**
     * @return array<string, mixed>
     */
    private function followUpPayload(FollowUp $followUp): array
    {
        return [
            'type' => 'crm_follow_up_reminder',
            'follow_up_id' => $followUp->id,
            'party_id' => $followUp->party_id,
            'channel' => $followUp->channel?->value,
            'scheduled_at' => $followUp->scheduled_at?->toIso8601String(),
            'message' => sprintf(
                'Follow-up (%s) with %s is due.',
                $followUp->channel?->label() ?? 'follow-up',
                $followUp->party?->name ?? 'a party',
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(Task $task): array
    {
        return [
            'type' => 'crm_task_reminder',
            'task_id' => $task->id,
            'title' => $task->title,
            'due_date' => $task->due_date?->toDateString(),
            'message' => sprintf('Task "%s" needs your attention.', $task->title),
        ];
    }
}
