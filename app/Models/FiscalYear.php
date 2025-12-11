<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'year_name',
        'year_code',
        'start_date',
        'end_date',
    ];
}
