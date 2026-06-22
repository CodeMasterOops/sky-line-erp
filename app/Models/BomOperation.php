<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomOperation extends Model
{
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'bom_id',
        'sequence',
        'name',
        'work_center',
        'duration_minutes',
        'cost_per_hour',
        'remarks',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'duration_minutes' => 'integer',
        'cost_per_hour' => 'float',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }
}
