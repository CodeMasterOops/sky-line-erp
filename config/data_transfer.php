<?php

/**
 * Data transfer (import/export) configuration.
 *
 * Production scaling:
 * - Set QUEUE_CONNECTION=redis and install Laravel Horizon for workers on
 *   queues: data-transfer, data-transfer-heavy, ird.
 * - Run: php artisan queue:work --queue=data-transfer-heavy,data-transfer,ird
 * - Schedule: data-transfer:prune (registered in routes/console.php)
 */
return [
    'disk' => env('DATA_TRANSFER_DISK', 'local'),

    'max_upload_bytes' => (int) env('DATA_TRANSFER_MAX_UPLOAD', 20 * 1024 * 1024),

    'chunk_size' => (int) env('DATA_TRANSFER_CHUNK_SIZE', 100),

    'retention_days' => (int) env('DATA_TRANSFER_RETENTION_DAYS', 14),

    'signed_url_minutes' => (int) env('DATA_TRANSFER_SIGNED_URL_MINUTES', 15),

    'uploads_per_hour' => (int) env('DATA_TRANSFER_UPLOADS_PER_HOUR', 10),

    'queue' => env('DATA_TRANSFER_QUEUE', 'data-transfer'),

    'heavy_queue' => env('DATA_TRANSFER_HEAVY_QUEUE', 'data-transfer-heavy'),

    'allowed_mimes' => [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],

    'allowed_extensions' => ['csv', 'txt', 'xlsx'],

    'product_fields' => [
        'name',
        'code',
        'product_type',
        'hsn_code',
        'description',
        'category',
        'unit',
        'brand',
        'tax',
        'has_variants',
        'reorder_quantity',
        'min_stock_level',
        'sku',
        'sales_price',
        'purchase_price',
        'is_default',
        'attribute_1_name',
        'attribute_1_value',
        'attribute_2_name',
        'attribute_2_value',
        'warehouse',
        'quantity',
    ],
];
