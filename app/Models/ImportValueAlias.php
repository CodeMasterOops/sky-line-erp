<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportValueAlias extends Model
{
    protected $fillable = [
        'company_id',
        'entity_type',
        'field',
        'source_value',
        'target_id',
        'target_value',
        'created_by',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
