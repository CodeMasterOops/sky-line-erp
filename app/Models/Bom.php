<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bom extends Model
{
    use MultiTenant, SoftDeletes;

    protected $table = 'boms';

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'name',
        'version',
        'output_qty',
        'output_unit_id',
        'is_active',
        'is_default',
        'remarks',
    ];

    protected $casts = [
        'output_qty' => 'float',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function outputUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'output_unit_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class);
    }
}
