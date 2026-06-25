<?php

/**
 * Default HR master data provisioned for every new company.
 * Nepal-specific defaults (Saturday weekly off, public holidays, etc.).
 */
return [

    'departments' => [
        ['name' => 'Administration', 'code' => 'ADM'],
        ['name' => 'Finance & Accounts', 'code' => 'FIN'],
        ['name' => 'Sales & Marketing', 'code' => 'SAL'],
        ['name' => 'Human Resources', 'code' => 'HRM'],
        ['name' => 'Operations', 'code' => 'OPS'],
        ['name' => 'IT & Technology', 'code' => 'ITS'],
        ['name' => 'Procurement', 'code' => 'PRO'],
    ],

    'designations' => [
        ['name' => 'Managing Director'],
        ['name' => 'General Manager'],
        ['name' => 'Deputy General Manager'],
        ['name' => 'Manager'],
        ['name' => 'Assistant Manager'],
        ['name' => 'Senior Officer'],
        ['name' => 'Officer'],
        ['name' => 'Junior Officer'],
        ['name' => 'Assistant'],
        ['name' => 'Intern'],
    ],

    'leave_types' => [
        ['name' => 'Annual Leave', 'days_allowed' => 18, 'is_paid' => true],
        ['name' => 'Sick Leave', 'days_allowed' => 12, 'is_paid' => true],
        ['name' => 'Maternity Leave', 'days_allowed' => 98, 'is_paid' => true],
        ['name' => 'Paternity Leave', 'days_allowed' => 15, 'is_paid' => true],
        ['name' => 'Casual Leave', 'days_allowed' => 12, 'is_paid' => true],
        ['name' => 'Unpaid Leave', 'days_allowed' => 30, 'is_paid' => false],
        ['name' => 'Bereavement Leave', 'days_allowed' => 13, 'is_paid' => true],
    ],

    'work_schedule' => [
        'name' => 'Standard (Sun–Fri)',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'grace_minutes' => 15,
        'standard_hours_per_day' => 8.0,
        'overtime_multiplier' => 1.5,
        'overtime_enabled' => true,
        'weekly_off_days' => [6], // 6 = Saturday (Carbon dayOfWeek)
        'is_default' => true,
    ],

    /**
     * Major Nepal public holidays (AD dates, year-agnostic — month/day only).
     * Provisioning step will use the current year to build actual dates.
     * Format: ['name', 'month' (1-12 AD), 'day' (1-31 AD)].
     */
    'public_holidays' => [
        ['name' => 'New Year\'s Day (Naya Barsha)', 'month' => 1, 'day' => 1],
        ['name' => 'Prithivi Narayan Shah Birthday', 'month' => 1, 'day' => 11],
        ['name' => 'Democracy Day (Prajatantra Diwas)', 'month' => 2, 'day' => 19],
        ['name' => 'International Labour Day', 'month' => 5, 'day' => 1],
        ['name' => 'Republic Day (Ganatantra Diwas)', 'month' => 5, 'day' => 29],
        ['name' => 'Constitution Day (Sambidhan Diwas)', 'month' => 9, 'day' => 20],
        ['name' => 'Christmas Day', 'month' => 12, 'day' => 25],
    ],

    'salary_components' => [
        [
            'name' => 'Basic Salary',
            'type' => 'earning',
            'is_basic' => true,
            'calculation_type' => 'fixed',
            'is_taxable' => true,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'BASIC',
        ],
        [
            'name' => 'House Rent Allowance',
            'type' => 'earning',
            'is_basic' => false,
            'calculation_type' => 'percentage',
            'percentage_base' => 'BASIC',
            'is_taxable' => true,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'HRA',
        ],
        [
            'name' => 'Transport Allowance',
            'type' => 'earning',
            'is_basic' => false,
            'calculation_type' => 'fixed',
            'is_taxable' => false,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'TA',
        ],
        [
            'name' => 'Social Security Fund (Employee)',
            'type' => 'deduction',
            'is_basic' => false,
            'calculation_type' => 'percentage',
            'percentage_base' => 'BASIC',
            'is_taxable' => false,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'SSF_EMP',
        ],
        [
            'name' => 'Social Security Fund (Employer)',
            'type' => 'employer_contribution',
            'is_basic' => false,
            'calculation_type' => 'percentage',
            'percentage_base' => 'BASIC',
            'is_taxable' => false,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'SSF_EMP_CONT',
        ],
        [
            'name' => 'Income Tax (TDS)',
            'type' => 'deduction',
            'is_basic' => false,
            'calculation_type' => 'fixed',
            'is_taxable' => false,
            'is_active' => true,
            'is_system' => true,
            'system_code' => 'TDS',
        ],
    ],

];
