<?php

namespace App\Models;

use App\Traits\MultiTenant;
use App\Traits\BranchTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosHeldOrder extends Model
{
    use BranchTenant;
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'branch_id',
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
