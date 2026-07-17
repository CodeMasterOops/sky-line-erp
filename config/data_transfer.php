<?php

/**
 * Data transfer (import/export) configuration.
 *
 * Queues: set QUEUE_CONNECTION=redis and run Redis (see .env.example, docker-compose.redis.yml).
 * Workers must listen on named queues (not only "default"):
 * - Local: `composer run dev` runs `queue:listen-app`
 * - Server/Docker: supervisor runs `queue:work-app` (see docker/supervisor)
 * - Verify Redis: `php artisan redis:ping` and `redis:ping --connection=queue`
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
        'barcode',
        'sales_price',
        'purchase_price',
        'is_default',
        'attribute_1_name',
        'attribute_1_value',
        'attribute_2_name',
        'attribute_2_value',
    ],

    'opening_stock_fields' => [
        'product_code',
        'sku',
        'barcode',
        'warehouse',
        'quantity',
        'rate',
        'batch_no',
        'expiry_date',
        'remarks',
    ],

    'warehouse_fields' => [
        'name',
        'code',
        'parent',
        'phone',
        'address',
    ],

    'party_fields' => [
        'type',
        'name',
        'code',
        'phone',
        'email',
        'pan',
        'address',
        'credit_limit',
        'is_active',
    ],
];
