<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\ChangeTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'reference_type',
        'reference_id',
        'user_id',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'type' => ChangeTypeEnum::class,
    ];
}
