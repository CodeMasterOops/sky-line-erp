<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class DataTransferMapping extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'name',
        'entity_type',
        'mapping',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
