<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use MultiTenant;

    protected $fillable = [
        'company_id',
        'name',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function scopeFilter($query, $param = [])
    {
        if (! empty($param['year'])) {
            $query->whereYear('date', $param['year']);
        }

        if (! empty($param['month'])) {
            $query->whereMonth('date', $param['month']);
        }

        return $query;
    }
}
