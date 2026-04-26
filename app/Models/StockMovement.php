<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Enums\ChangeTypeEnum;
use App\Enums\StockDirectionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'product_variant_id',
        'warehouse_id',
        'bin_id',
        'type',
        'direction',
        'quantity',
        'reference_type',
        'reference_id',
        'user_id',
        'remarks',
        'unit_cost',
        'total_cost',
        'gl_journal_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'type' => ChangeTypeEnum::class,
        'direction' => StockDirectionEnum::class,
        'unit_cost' => 'float',
        'total_cost' => 'float',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function movementLayers(): HasMany
    {
        return $this->hasMany(StockMovementLayer::class);
    }

    public function glJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'gl_journal_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }
}
