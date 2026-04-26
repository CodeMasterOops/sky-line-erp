<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bin extends Model
{
    use MultiTenant, SoftDeletes;

    /** System default storage bin per warehouse (Phase 1 stock rows use this). */
    public const DEFAULT_CODE = '__DEFAULT__';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'name',
        'code',
        'zone',
        'rack',
        'level',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public static function defaultIdForWarehouse(int $companyId, int $warehouseId): int
    {
        $bin = static::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('code', self::DEFAULT_CODE)
            ->firstOrFail();

        return (int) $bin->id;
    }
}
