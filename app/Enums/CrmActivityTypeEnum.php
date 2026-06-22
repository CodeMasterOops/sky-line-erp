<?php

namespace App\Enums;

enum CrmActivityTypeEnum: string
{
    case LeadCreated = 'lead_created';
    case StatusChanged = 'status_changed';
    case NoteAdded = 'note_added';
    case FollowUpScheduled = 'follow_up_scheduled';
    case FollowUpCompleted = 'follow_up_completed';
    case TaskCreated = 'task_created';
    case TaskCompleted = 'task_completed';
    case Converted = 'converted';
    case Assigned = 'assigned';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'Lead Created',
            self::StatusChanged => 'Status Changed',
            self::NoteAdded => 'Note Added',
            self::FollowUpScheduled => 'Follow-up Scheduled',
            self::FollowUpCompleted => 'Follow-up Completed',
            self::TaskCreated => 'Task Created',
            self::TaskCompleted => 'Task Completed',
            self::Converted => 'Converted',
            self::Assigned => 'Assigned',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
