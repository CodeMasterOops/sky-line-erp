<?php

namespace App\Enums;

use App\Notifications\DataTransferCompletedNotification;

enum NotificationTypeEnum: string
{
    case Registration = 'registration';
    case DataTransferCompleted = 'data_transfer_completed';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::Registration => 'Registration',
            self::DataTransferCompleted => 'Data Transfer Completed',
        };
    }

    public static function fromNotificationClass(string $class): ?self
    {
        return match ($class) {
            DataTransferCompletedNotification::class => self::DataTransferCompleted,
            default => null,
        };
    }
}
