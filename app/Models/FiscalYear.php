<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (self $fy) {
            if ($fy->start_date && $fy->end_date && $fy->start_date >= $fy->end_date) {
                throw new \InvalidArgumentException('Fiscal year start_date must be before end_date.');
            }
        });
    }

    protected $fillable = [
        'year_name',
        'year_code',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];
}
