<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Warehouse $warehouse) {
            Bin::firstOrCreate(
                [
                    'company_id' => $warehouse->company_id,
                    'warehouse_id' => $warehouse->id,
                    'code' => Bin::DEFAULT_CODE,
                ],
                [
                    'name' => 'Default',
                    'is_active' => true,
                ],
            );
        });
    }

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'code',
        'phone',
        'address',
    ];

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }
}
