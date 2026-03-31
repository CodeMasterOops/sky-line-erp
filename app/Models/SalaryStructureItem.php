<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructureItem extends Model
{
    protected $fillable = [
        'salary_structure_id',
        'salary_component_id',
        'amount',
        'percentage',
    ];

    protected $casts = [
        'amount' => 'float',
        'percentage' => 'float',
    ];

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function salaryComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class);
    }
}
