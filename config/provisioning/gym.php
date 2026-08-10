<?php

/**
 * Defaults created for a company the first time the Gym module is switched on.
 *
 * Every one of these is written with firstOrCreate, because enable → disable →
 * enable is a supported cycle and must never duplicate anything.
 */
return [

    /**
     * The four standard terms a gym sells. `preset` fills in the duration, so
     * these stay correct even if the presets are re-tuned later.
     */
    'membership_plans' => [
        ['name' => 'Monthly', 'preset' => 'monthly', 'price' => 2000, 'sort_order' => 10],
        ['name' => 'Quarterly', 'preset' => 'quarterly', 'price' => 5500, 'sort_order' => 20],
        ['name' => 'Half-Yearly', 'preset' => 'half_yearly', 'price' => 10000, 'sort_order' => 30],
        ['name' => 'Yearly', 'preset' => 'yearly', 'price' => 18000, 'sort_order' => 40],
    ],

    /** Product category the membership service items are filed under. */
    'product_category' => 'Memberships',

    /** Roles created alongside the module, added to the company's role list. */
    'roles' => [
        [
            'name' => 'Gym Manager',
            'permissions' => [
                'list_member', 'create_member', 'edit_member', 'delete_member', 'show_member',
                'list_membership_plan', 'create_membership_plan', 'edit_membership_plan', 'delete_membership_plan',
                'list_party', 'show_party', 'create_party', 'edit_party',
                'list_product', 'list_invoice', 'create_invoice', 'show_invoice',
                'list_receipt', 'create_receipt',
            ],
        ],
        [
            'name' => 'Front Desk',
            'permissions' => [
                'list_member', 'create_member', 'edit_member', 'show_member',
                'list_membership_plan',
                'list_party', 'show_party',
            ],
        ],
    ],

    /**
     * Per-company module settings, merged over anything the company has set.
     * Consumed from Phase 6 onwards (assignment, renewal, reminders).
     */
    'module_settings' => [
        'auto_invoice_on_assignment' => true,
        'allow_multiple_active_memberships' => false,
        'lapsed_renewal_continues_term' => false,
        'notify_member_directly' => false,
    ],

];
