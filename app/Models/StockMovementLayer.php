<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovementLayer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'stock_movement_id',
        'stock_layer_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
    ];

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function stockLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class);
    }
}
