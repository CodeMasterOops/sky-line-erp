<?php

/*
|--------------------------------------------------------------------------
| Company Categories — initial catalogue
|--------------------------------------------------------------------------
|
| Phase 1 of the Modular SaaS Platform plan
| (docs/saas-modular-platform-and-gym-module-plan.md §3.12).
|
| A category is the industry a company is in. It carries a default module set
| that is applied when the company is provisioned, and can be re-applied later.
| Categories are stored in the database (the Super Admin edits them from
| Phase 4 onwards); this file is the seed catalogue used by
| CompanyCategorySeeder, which only creates rows that are missing and never
| overwrites super-admin edits.
|
| `modules` lists the keys enabled by default, and must be dependency-closed:
| every module's `requires` (config/modules.php) has to appear in the same list.
| tests/Feature/Modules/CompanyCategorySeederTest.php enforces that — note in
| particular that `sales` and `purchase` require `inventory`, because products
| (including service items) live in the inventory module.
|
| `core` is deliberately absent: it is always on and the resolver adds it.
|
*/

return [

    [
        'name' => 'General Business',
        'slug' => 'general',
        'description' => 'A balanced starting point for most small and medium businesses.',
        'icon' => 'ti ti-building-store',
        'is_default' => true,
        'sort_order' => 10,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'crm', 'data-transfer'],
    ],

    [
        'name' => 'Retail',
        'slug' => 'retail',
        'description' => 'Shops and counters selling to walk-in customers.',
        'icon' => 'ti ti-shopping-cart',
        'sort_order' => 20,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'pos', 'crm', 'data-transfer'],
    ],

    [
        'name' => 'Wholesale / Distribution',
        'slug' => 'wholesale',
        'description' => 'Bulk trading with credit terms and heavy banking activity.',
        'icon' => 'ti ti-truck-delivery',
        'sort_order' => 30,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'crm', 'banking', 'data-transfer'],
    ],

    [
        'name' => 'Manufacturing',
        'slug' => 'manufacturing',
        'description' => 'Production from bills of material, with staff and shop-floor costing.',
        'icon' => 'ti ti-tools',
        'sort_order' => 40,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'manufacturing', 'hr', 'data-transfer'],
    ],

    [
        'name' => 'Service Business',
        'slug' => 'service',
        'description' => 'Billable services and projects rather than physical stock.',
        'icon' => 'ti ti-briefcase',
        'sort_order' => 50,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'crm', 'hr'],
    ],

    [
        'name' => 'Gym / Fitness',
        'slug' => 'gym',
        'description' => 'Membership-driven fitness centres.',
        'icon' => 'ti ti-barbell',
        'sort_order' => 60,
        'modules' => ['accounting', 'inventory', 'sales', 'purchase', 'crm', 'gym'],
    ],

    [
        'name' => 'NGO / Non-Profit',
        'slug' => 'ngo',
        'description' => 'Grant and budget driven organisations.',
        'icon' => 'ti ti-heart-handshake',
        'sort_order' => 70,
        'modules' => ['accounting', 'inventory', 'purchase', 'hr', 'budgeting'],
    ],

];
