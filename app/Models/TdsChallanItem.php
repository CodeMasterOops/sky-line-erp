<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsChallanItem extends Model
{
    protected $fillable = [
        'tds_challan_id',
        'tds_deduction_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
        ];
    }

    public function tdsChallan(): BelongsTo
    {
        return $this->belongsTo(TdsChallan::class);
    }

    public function tdsDeduction(): BelongsTo
    {
        return $this->belongsTo(TdsDeduction::class);
    }
}
