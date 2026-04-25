<?php

namespace App\Models;

use App\Enums\TaxTypeEnum;
use App\Enums\TdsCategoryEnum;
use Illuminate\Database\Eloquent\Model;

class TaxTemplate extends Model
{
    protected $fillable = [
        'name',
        'rate',
        'type',
        'tds_category',
        'is_default',
        'description',
    ];

    protected $casts = [
        'rate'        => 'float',
        'is_default'  => 'boolean',
        'type'        => TaxTypeEnum::class,
        'tds_category' => TdsCategoryEnum::class,
    ];
}
