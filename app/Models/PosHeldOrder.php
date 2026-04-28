<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosHeldOrder extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'party_id',
        'label',
        'order_data',
    ];

    protected $casts = [
        'order_data' => 'array',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
