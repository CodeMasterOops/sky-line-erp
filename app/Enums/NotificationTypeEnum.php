<?php

namespace App\Enums;

use App\Notifications\LowStockNotification;
use App\Notifications\CrmReminderNotification;
use App\Notifications\MembershipExpiredNotification;
use App\Notifications\DataTransferCompletedNotification;
use App\Notifications\MemberMembershipExpiringNotification;
use App\Notifications\MembershipExpiryReminderNotification;

enum NotificationTypeEnum: string
{
    case Registration = 'registration';
    case DataTransferCompleted = 'data_transfer_completed';
    case LowStock = 'low_stock';
    case CrmReminder = 'crm_reminder';
    case MembershipExpiryReminder = 'membership_expiry_reminder';
    case MembershipExpired = 'membership_expired';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::Registration => 'Registration',
            self::DataTransferCompleted => 'Data Transfer Completed',
            self::LowStock => 'Low Stock Alert',
            self::CrmReminder => 'CRM Reminder',
            self::MembershipExpiryReminder => 'Membership Expiry Reminder',
            self::MembershipExpired => 'Membership Expired',
        };
    }

    /**
     * The module that owns this notification, or null when every company gets
     * it. A company that does not run the module must never receive it — the
     * dispatchers enforce that through `moduleEnabled()` or the
     * `SkipsDisabledModule` job middleware, and this is the map they agree on.
     */
    public function module(): ?string
    {
        return match ($this) {
            self::Registration => null,
            self::DataTransferCompleted => 'data-transfer',
            self::LowStock => 'inventory',
            self::CrmReminder => 'crm',
            self::MembershipExpiryReminder, self::MembershipExpired => 'gym',
        };
    }

    public static function fromNotificationClass(string $class): ?self
    {
        return match ($class) {
            DataTransferCompletedNotification::class => self::DataTransferCompleted,
            LowStockNotification::class => self::LowStock,
            CrmReminderNotification::class => self::CrmReminder,
            MembershipExpiryReminderNotification::class, MemberMembershipExpiringNotification::class => self::MembershipExpiryReminder,
            MembershipExpiredNotification::class => self::MembershipExpired,
            default => null,
        };
    }
}
