<?php

/**
 * Optional demo catalog for a new company (retail-friendly defaults).
 * Used by CompanyCatalogSeeder; safe to edit for your vertical.
 */
return [

    'units' => [
        ['name' => 'Piece', 'code' => 'PCS'],
        ['name' => 'Kilogram', 'code' => 'KG'],
        ['name' => 'Liter', 'code' => 'L'],
        ['name' => 'Hour', 'code' => 'HR'],
        ['name' => 'Day', 'code' => 'DAY'],
        ['name' => 'Job', 'code' => 'JOB'],
        ['name' => 'Fixed', 'code' => 'FIX'],
    ],

    'brands' => [
        ['name' => 'House Brand', 'code' => 'HB'],
    ],

    'categories' => [
        ['name' => 'General', 'description' => 'Miscellaneous items'],
        [
            'name' => 'Beverages',
            'description' => 'Drinks',
            'children' => [
                ['name' => 'Water', 'description' => 'Bottled water'],
                ['name' => 'Dairy Drinks', 'description' => 'Milk and dairy beverages'],
            ],
        ],
        [
            'name' => 'Grocery',
            'description' => 'Packaged foods',
            'children' => [
                ['name' => 'Staples', 'description' => 'Rice, flour, and staples'],
            ],
        ],
        ['name' => 'Services', 'description' => 'Billable services and labor'],
    ],

    /**
     * category: leaf category name (or Parent > Child path)
     * unit_code, brand_code: match codes from units / brands
     */
    'products' => [
        [
            'name' => 'Mineral Water 1L',
            'code' => 'DEMO-WAT-1L',
            'category' => 'Water',
            'unit_code' => 'PCS',
            'brand_code' => 'HB',
            'hsn_code' => null,
            'sales_price' => 25.0,
            'purchase_price' => 15.0,
            'sku' => 'DEMO-SKU-WAT-1L',
        ],
        [
            'name' => 'Rice 5Kg',
            'code' => 'DEMO-RICE-5K',
            'category' => 'Staples',
            'unit_code' => 'PCS',
            'brand_code' => 'HB',
            'hsn_code' => null,
            'sales_price' => 750.0,
            'purchase_price' => 600.0,
            'sku' => 'DEMO-SKU-RICE-5K',
        ],
        [
            'name' => 'Milk 1L',
            'code' => 'DEMO-MILK-1L',
            'category' => 'Dairy Drinks',
            'unit_code' => 'L',
            'brand_code' => 'HB',
            'hsn_code' => null,
            'sales_price' => 90.0,
            'purchase_price' => 75.0,
            'sku' => 'DEMO-SKU-MILK-1L',
        ],
        [
            'name' => 'Notebook A4',
            'code' => 'DEMO-NOTE-A4',
            'category' => 'General',
            'unit_code' => 'PCS',
            'brand_code' => 'HB',
            'hsn_code' => null,
            'sales_price' => 120.0,
            'purchase_price' => 80.0,
            'sku' => 'DEMO-SKU-NOTE-A4',
        ],
    ],

    /**
     * Billable services (no stock, single price row).
     */
    'services' => [
        [
            'name' => 'Installation Service',
            'code' => 'DEMO-SVC-INST',
            'category' => 'Services',
            'unit_code' => 'JOB',
            'hsn_code' => null,
            'sales_price' => 1500.0,
            'purchase_price' => 800.0,
            'sku' => 'DEMO-SKU-SVC-INST',
        ],
        [
            'name' => 'Annual Maintenance',
            'code' => 'DEMO-SVC-MAINT',
            'category' => 'Services',
            'unit_code' => 'FIX',
            'hsn_code' => null,
            'sales_price' => 5000.0,
            'purchase_price' => 0.0,
            'sku' => 'DEMO-SKU-SVC-MAINT',
        ],
        [
            'name' => 'Consulting (Hourly)',
            'code' => 'DEMO-SVC-CONS',
            'category' => 'Services',
            'unit_code' => 'HR',
            'hsn_code' => null,
            'sales_price' => 2500.0,
            'purchase_price' => 1200.0,
            'sku' => 'DEMO-SKU-SVC-CONS',
        ],
    ],

    'parties' => [
        [
            'type' => 'customer',
            'name' => 'Walk-in Customer',
            'code' => 'DEMO-CUST-01',
            'phone' => '9800000000',
            'email' => 'walkin@example.com',
            'address' => 'Local',
        ],
        [
            'type' => 'customer',
            'name' => 'Sample Retail Account',
            'code' => 'DEMO-CUST-02',
            'phone' => '9800000001',
            'email' => 'retail@example.com',
            'address' => 'Kathmandu',
        ],
        [
            'type' => 'supplier',
            'name' => 'Demo Supplier Co.',
            'code' => 'DEMO-SUP-01',
            'phone' => '01-1234567',
            'email' => 'orders@demosupplier.com',
            'address' => 'Demo Industrial Area',
        ],
    ],

    /** Extra warehouses in addition to company_bootstrap default (e.g. Main). */
    'additional_warehouses' => [
        ['name' => 'Store Front', 'code' => 'STORE-01'],
    ],

];
