<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One default module of a company category. Kept as rows rather than a JSON
 * column so the Super Admin UI can edit them one at a time and so "which
 * categories ship the gym module?" stays a query.
 */
class CompanyCategoryModule extends Model
{
    protected $fillable = [
        'company_category_id',
        'module_key',
        'is_default_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CompanyCategory::class, 'company_category_id');
    }
}
