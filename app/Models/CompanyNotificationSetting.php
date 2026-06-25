<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyNotificationSetting extends Model
{
    use MultiTenant;

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
            'email_notifications' => 'boolean',
            'in_app_notifications' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
