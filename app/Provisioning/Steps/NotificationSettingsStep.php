<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyNotificationSetting;
use App\Provisioning\Contracts\ProvisioningStep;

class NotificationSettingsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'NotificationSettings';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $cfg = config('provisioning.notifications');

        CompanyNotificationSetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'low_stock_alert' => $cfg['low_stock_alert'],
                'invoice_due_reminder' => $cfg['invoice_due_reminder'],
                'invoice_due_reminder_days' => $cfg['invoice_due_reminder_days'],
                'bill_due_reminder' => $cfg['bill_due_reminder'],
                'bill_due_reminder_days' => $cfg['bill_due_reminder_days'],
                'payroll_processed_alert' => $cfg['payroll_processed_alert'],
                'leave_approval_alert' => $cfg['leave_approval_alert'],
                'stock_expiry_alert' => $cfg['stock_expiry_alert'],
                'stock_expiry_days' => $cfg['stock_expiry_days'],
                'email_notifications' => $cfg['email_notifications'],
                'in_app_notifications' => $cfg['in_app_notifications'],
            ],
        );
    }
}
