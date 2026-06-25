<?php

/**
 * Default notification settings provisioned for every new company.
 */
return [

    'low_stock_alert' => true,
    'invoice_due_reminder' => true,
    'invoice_due_reminder_days' => [7, 3, 1],
    'bill_due_reminder' => true,
    'bill_due_reminder_days' => [7, 3, 1],
    'payroll_processed_alert' => true,
    'leave_approval_alert' => true,
    'stock_expiry_alert' => true,
    'stock_expiry_days' => 30,
    'email_notifications' => true,
    'in_app_notifications' => true,

];
