<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxGroupMember extends Model
{
    protected $fillable = [
        'tax_group_id',
        'tax_id',
        'sequence',
    ];

    protected $casts = [
        'tax_group_id' => 'integer',
        'tax_id' => 'integer',
        'sequence' => 'integer',
    ];

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }
}
