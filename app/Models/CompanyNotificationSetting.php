<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyNotificationSetting extends Model
{
    use MultiTenant;

    /**
     * Which module each alert belongs to. A setting with no entry is core.
     *
     * The flag and the module answer two different questions — "does this
     * company want this alert?" and "does this company have this feature at
     * all?" — so the module is checked at dispatch rather than written into the
     * flag. Switching a module off silences its alerts; switching it back on
     * restores whatever the company had chosen, untouched.
     *
     * @var array<string, string>
     */
    public const SETTING_MODULES = [
        'low_stock_alert' => 'inventory',
        'stock_expiry_alert' => 'inventory',
        'invoice_due_reminder' => 'sales',
        'bill_due_reminder' => 'purchase',
        'payroll_processed_alert' => 'payroll',
        'leave_approval_alert' => 'hr',
        'membership_expiry_reminder' => 'gym',
        'membership_expired_alert' => 'gym',
    ];

    protected $fillable = [
        'company_id',
        'low_stock_alert',
        'invoice_due_reminder',
        'invoice_due_reminder_days',
        'bill_due_reminder',
        'bill_due_reminder_days',
        'payroll_processed_alert',
        'leave_approval_alert',
        'stock_expiry_alert',
        'stock_expiry_days',
        'membership_expiry_reminder',
        'membership_expiry_reminder_days',
        'membership_expired_alert',
        'email_notifications',
        'in_app_notifications',
    ];

    protected function casts(): array
    {
        return [
            'low_stock_alert' => 'boolean',
            'invoice_due_reminder' => 'boolean',
            'invoice_due_reminder_days' => 'array',
            'bill_due_reminder' => 'boolean',
            'bill_due_reminder_days' => 'array',
            'payroll_processed_alert' => 'boolean',
            'leave_approval_alert' => 'boolean',
            'stock_expiry_alert' => 'boolean',
            'stock_expiry_days' => 'integer',
            'membership_expiry_reminder' => 'boolean',
            'membership_expiry_reminder_days' => 'array',
            'membership_expired_alert' => 'boolean',
            'email_notifications' => 'boolean',
            'in_app_notifications' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Whether this alert should actually be sent: the company asked for it AND
     * still runs the module that owns it.
     */
    public function isActive(string $setting): bool
    {
        if (! (bool) ($this->{$setting} ?? false)) {
            return false;
        }

        $moduleKey = self::SETTING_MODULES[$setting] ?? null;

        return $moduleKey === null || moduleEnabled($moduleKey, (int) $this->company_id);
    }

    /**
     * The alerts worth offering this company — the module-owned ones it can
     * actually receive. Drives any settings screen; a hidden setting keeps its
     * stored value.
     *
     * @return list<string>
     */
    public function availableSettings(): array
    {
        return array_values(array_filter(
            array_keys(self::SETTING_MODULES),
            fn (string $setting): bool => moduleEnabled(self::SETTING_MODULES[$setting], (int) $this->company_id),
        ));
    }
}
